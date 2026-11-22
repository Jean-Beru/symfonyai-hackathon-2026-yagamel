import { Controller } from '@hotwired/stimulus';

/*
 * The videos in progress, from Mercure: one card per video with a frame, the goals and the field, the path of the ball
 * and the decisions. The API sends one frame at a time: the page keeps the frames and the path.
 * The slider of a card shows a previous frame and the path up to it. At the end, the card follows the new frames.
 */
export default class extends Controller {
    static targets = ['videos', 'card', 'status'];
    static values = { hub: String, pathSeconds: Number };

    connect() {
        this.videos = new Map();
        this.source = new EventSource(this.hubValue);
        this.source.onopen = () => { this.statusTarget.textContent = 'Live'; };
        this.source.onerror = () => { this.statusTarget.textContent = 'Disconnected, retrying'; };
        this.source.onmessage = (event) => this.show(JSON.parse(event.data));
    }

    disconnect() {
        this.source.close();
    }

    show(frame) {
        const video = this.videos.get(frame.video_id) ?? this.add(frame.video_id);
        video.frames.push({ image: frame.image, timestamp: frame.timestamp, ball: frame.ball?.center ?? null });
        video.table ??= frame.table;
        if (frame.ball) video.balls += 1;
        if (frame.decision) {
            const item = document.createElement('li');
            const call = frame.decision.call[0].toUpperCase() + frame.decision.call.slice(1);
            item.textContent = `${this.time(frame.timestamp)} ${call} in the ${frame.decision.goal} goal`;
            video.decisions.append(item);
        }
        video.slider.max = video.frames.length - 1;
        if (video.following) video.slider.value = video.slider.max;
        this.render(video, Number(video.slider.value));

        video.fields.frames.textContent = video.frames.length;
        video.fields.ball.textContent = `${video.balls} (${Math.round(100 * video.balls / video.frames.length)} %)`;
        video.fields.ms.textContent = `${frame.processing_ms} ms`;
    }

    seek({ target }) {
        const video = this.videos.get(target.dataset.video);
        video.following = Number(target.value) === video.frames.length - 1;
        this.render(video, Number(target.value));
    }

    add(id) {
        const card = this.cardTarget.content.firstElementChild.cloneNode(true);
        const fields = Object.fromEntries([...card.querySelectorAll('[data-field]')].map((element) => [element.dataset.field, element]));
        fields.id.textContent = id;
        const video = {
            image: card.querySelector('img'),
            context: card.querySelector('canvas').getContext('2d'),
            slider: card.querySelector('input[type=range]'),
            decisions: card.querySelector('.decisions'),
            fields,
            frames: [],
            balls: 0,
            table: null,
            following: true,
        };
        video.slider.dataset.video = id;
        this.videosTarget.querySelector('.empty')?.remove();
        this.videosTarget.prepend(card);
        this.videos.set(id, video);

        return video;
    }

    // The frame at this index, and the path from the first frame up to it.
    render(video, index) {
        const frame = video.frames[index];
        if (video.image.getAttribute('src') !== frame.image) video.image.src = frame.image;
        video.fields.position.textContent = `${index + 1} / ${video.frames.length}, ${(frame.timestamp / 1000).toFixed(2)} s`;

        const context = video.context;
        context.clearRect(0, 0, context.canvas.width, context.canvas.height);
        if (video.table) this.drawTable(context, video.table, frame.ball);
        this.drawPath(context, video.frames, index);
    }

    // 8150 ms: 8'15.
    time(timestamp) {
        return `${Math.floor(timestamp / 1000)}'${String(Math.floor(timestamp % 1000 / 10)).padStart(2, '0')}`;
    }

    // The goal that holds the ball of the frame is yellow, the other zones are white.
    drawTable(context, table, ball) {
        context.lineWidth = 2;
        for (const [zone, [x1, y1, x2, y2]] of Object.entries(table)) {
            const holdsBall = zone !== 'field' && ball && ball[0] >= x1 && ball[0] <= x2 && ball[1] >= y1 && ball[1] <= y2;
            context.strokeStyle = holdsBall ? '#ffd60a' : '#fff';
            if (holdsBall) {
                context.fillStyle = 'rgb(255 214 10 / 40%)';
                context.fillRect(x1, y1, x2 - x1, y2 - y1);
            }
            context.strokeRect(x1, y1, x2 - x1, y2 - y1);
        }
    }

    // The path over the last seconds up to the frame shown: one segment between two balls in a row, from blue at the
    // oldest frame to red at the frame shown.
    drawPath(context, frames, index) {
        const from = frames[index].timestamp - 1000 * this.pathSecondsValue;
        let first = index;
        while (first > 0 && frames[first - 1].timestamp >= from) first--;
        const span = Math.max(1, index - first);

        context.lineWidth = 3;
        context.lineCap = 'round';
        let previous = null;
        for (let i = first; i <= index; i++) {
            const ball = frames[i].ball;
            if (!ball) continue;
            if (previous) {
                context.strokeStyle = `hsl(${240 * (1 - (i - first) / span)} 100% 50%)`;
                context.beginPath();
                context.moveTo(...previous);
                context.lineTo(...ball);
                context.stroke();
            }
            previous = ball;
        }
    }
}
