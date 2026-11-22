"""Sends a video to POST /frames of the API, one frame after the other, in order. The API does everything else: it
stores the frame, finds the goals and the ball, decides a goal or a gamelle and updates the live page.
"""
import argparse
import json
import pathlib
import time

import cv2
import requests

# (connect, read), in seconds.
TIMEOUT_SECONDS = (2, 30)


def main() -> None:
  parser = argparse.ArgumentParser(description=__doc__)
  parser.add_argument('source', help='a video file or an RTSP URL')
  parser.add_argument('--url', default='http://php:3000/frames', help='POST /frames of the API')
  parser.add_argument('--video-id', help='default: the name of the file and the time, a new video for each run')
  parser.add_argument('--seconds', type=float, default=20, help='length to send, in seconds')
  args = parser.parse_args()

  video_id = args.video_id or f'{pathlib.Path(args.source).stem}-{int(time.time())}'
  capture = cv2.VideoCapture(args.source)
  fps = capture.get(cv2.CAP_PROP_FPS) or 60
  session = requests.Session()
  start = time.monotonic()
  frames = 0
  decisions = []
  while frames < args.seconds * fps:
    (ok, frame) = capture.read()
    if not ok:
      break
    timestamp = round(frames * 1000 / fps)
    answer = session.post(
      args.url,
      data={'video_id': video_id, 'timestamp': timestamp},
      files={'image': ('frame.jpg', cv2.imencode('.jpg', frame)[1].tobytes(), 'image/jpeg')},
      timeout=TIMEOUT_SECONDS,
    )
    answer.raise_for_status()
    if (decision := answer.json()['decision']):
      decisions.append({'timestamp': timestamp, **decision})
      print(f'{timestamp / 1000:.2f} s: {decision["call"]} in the {decision["goal"]} goal', flush=True)
    frames += 1

  total_s = time.monotonic() - start
  print(json.dumps({
    'video_id': video_id,
    'frames': frames,
    'video_s': round(frames / fps, 2),
    'total_s': round(total_s, 2),
    'ratio': round(total_s * fps / max(frames, 1), 2),
    'decisions': decisions,
  }, indent=2))


if __name__ == '__main__':
  main()
