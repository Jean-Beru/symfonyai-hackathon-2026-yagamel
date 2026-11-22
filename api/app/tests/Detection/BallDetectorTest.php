<?php

namespace App\Tests\Detection;

use App\Detection\BallDetector;
use App\Tests\Yolo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BallDetectorTest extends TestCase
{
    public function testRealAnswerOfTheModel(): void
    {
        $detector = $this->detector([new MockResponse((string) file_get_contents(__DIR__.'/../Fixtures/yolo_response.json'))]);

        $candidates = $detector->detect('a');

        self::assertCount(1, $candidates);
        ['box' => $box, 'center' => $center, 'confidence' => $confidence] = $candidates[0];
        self::assertEqualsWithDelta([303.03, 524.72, 319.04, 540.65], $box, 0.01);
        self::assertEqualsWithDelta([311.03, 532.69], $center, 0.01);
        self::assertEqualsWithDelta(0.7885, $confidence, 0.0001);
    }

    public function testBestConfidenceFirst(): void
    {
        $detector = $this->detector([Yolo::answer([[100, 200, 120, 230, 0.4, 0], [300, 400, 320, 420, 0.9, 0]])]);

        self::assertSame([
            ['box' => [300.0, 400.0, 320.0, 420.0], 'center' => [310.0, 410.0], 'confidence' => 0.9],
            ['box' => [100.0, 200.0, 120.0, 230.0], 'center' => [110.0, 215.0], 'confidence' => 0.4],
        ], $detector->detect('a'));
    }

    public function testConfidenceAtOrUnderTheMinimumIsDropped(): void
    {
        $detector = $this->detector([Yolo::answer([[1, 2, 3, 4, 0.1, 0], [1, 2, 3, 4, 0.05, 0]])]);

        self::assertSame([], $detector->detect('a'));
    }

    public function testAtMostMaxCandidates(): void
    {
        $detector = $this->detector([Yolo::answer([[1, 1, 2, 2, 0.3, 0], [1, 1, 2, 2, 0.5, 0], [1, 1, 2, 2, 0.4, 0]])], maxCandidates: 2);

        self::assertSame([0.5, 0.4], array_column($detector->detect('a'), 'confidence'));
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function detector(array $responses, int $maxCandidates = 10): BallDetector
    {
        return new BallDetector(Yolo::platform($responses), 0.1, $maxCandidates);
    }
}
