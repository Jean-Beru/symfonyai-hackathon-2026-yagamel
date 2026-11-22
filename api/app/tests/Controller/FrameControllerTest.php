<?php

namespace App\Tests\Controller;

use App\Frame\FrameStorage;
use App\Tests\Yolo;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class FrameControllerTest extends WebTestCase
{
    // The table of the fixture: top goal (224, 84, 328, 112), bottom goal (232, 712, 332, 752), field (108, 120, 448, 716).
    private const BALL_IN_TOP_GOAL = [268, 90, 284, 106, 0.8, 0];
    private const BALL_ON_THE_FIELD = [300, 400, 316, 416, 0.8, 0];

    /**
     * @var list<Update> the updates sent to the Mercure hub
     */
    private array $updates = [];

    public function testAnalyzesStoresAndPublishesTheFrame(): void
    {
        $client = $this->client([Yolo::answer([[10, 10, 26, 26, 0.9, 0], [20, 400, 36, 416, 0.9, 0], self::BALL_ON_THE_FIELD, [1, 1, 5, 5, 0.2, 0]])]);
        $videoId = $this->videoId();

        $this->post($client, $videoId, 1234);

        self::assertResponseIsSuccessful();
        $answer = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($videoId, $answer['video_id']);
        self::assertSame(1234, $answer['timestamp']);
        self::assertSame(['top' => [224, 84, 328, 112], 'bottom' => [232, 712, 332, 752], 'field' => [108, 120, 448, 716]], $answer['table']);
        // The best candidates are out of the table, a spare ball and a hand: the ball is the next one.
        self::assertEquals(['box' => [300, 400, 316, 416], 'center' => [308, 408], 'confidence' => 0.8], $answer['ball']);
        self::assertNull($answer['decision']);

        self::assertCount(1, $this->updates);
        self::assertSame(['videos'], $this->updates[0]->getTopics());
        $published = json_decode($this->updates[0]->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame("/videos/$videoId/1234.jpeg", $published['image']);
        self::assertSame($answer['ball'], $published['ball']);

        // The frame as sent to the model, served by Caddy on /videos/.
        $stored = static::getContainer()->get(FrameStorage::class)->path($videoId, 1234);
        self::assertSame([544, 960], \array_slice((array) getimagesize($stored), 0, 2));
    }

    public function testGoalAfterFramesWithoutTheBall(): void
    {
        // GOAL_MISSING_FRAMES=2 in .env.test.
        $client = $this->client([Yolo::answer([self::BALL_IN_TOP_GOAL]), Yolo::answer([]), Yolo::answer([])]);
        $client->disableReboot();

        $videoId = $this->videoId();
        $decisions = [];
        foreach ([0, 17, 33] as $timestamp) {
            $this->post($client, $videoId, $timestamp);
            $decisions[] = json_decode((string) $client->getResponse()->getContent(), true)['decision'];
        }

        self::assertSame([null, null, ['call' => 'goal', 'goal' => 'top']], $decisions);
    }

    public function testInvalidVideoId(): void
    {
        $this->post($this->client([]), '../etc', 0);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testImageMissing(): void
    {
        $client = $this->client([]);
        $client->request('POST', '/frames', ['video_id' => 'video', 'timestamp' => '0']);

        self::assertResponseStatusCodeSame(422);
    }

    public function testYoloTimeout(): void
    {
        $this->post($this->client([static fn () => throw new TimeoutException('Idle timeout reached')]), $this->videoId(), 0);

        self::assertResponseStatusCodeSame(504);
        self::assertCount(0, $this->updates);
    }

    public function testLivePageSubscribesToTheVideos(): void
    {
        $this->client([])->request('GET', '/live');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/.well-known/mercure?match=videos" data-live-path-seconds-value="5"', (string) static::getClient()->getResponse()->getContent());
    }

    /**
     * The state of the referee stays in the cache between the tests: one video per test.
     */
    private function videoId(): string
    {
        return 'test-'.bin2hex(random_bytes(4));
    }

    /**
     * @param list<MockResponse|\Closure> $responses
     */
    private function client(array $responses): KernelBrowser
    {
        $client = static::createClient();
        static::getContainer()->set(PlatformInterface::class, Yolo::platform($responses));
        static::getContainer()->set(HubInterface::class, new MockHub('http://hub/.well-known/mercure', new StaticTokenProvider('token'), function (Update $update): string {
            $this->updates[] = $update;

            return 'id';
        }));

        return $client;
    }

    private function post(KernelBrowser $client, string $videoId, int $timestamp): void
    {
        $image = new UploadedFile(__DIR__.'/../Fixtures/goal1_00000.jpg', 'frame.jpg', 'image/jpeg', null, true);
        $client->request('POST', '/frames', ['video_id' => $videoId, 'timestamp' => (string) $timestamp], ['image' => $image]);
    }
}
