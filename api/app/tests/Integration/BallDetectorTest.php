<?php

namespace App\Tests\Integration;

use App\Detection\BallDetector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Calls the real model in OpenVINO Model Server (YOLO_URL): see README.md.
 */
final class BallDetectorTest extends KernelTestCase
{
    /**
     * Test images of the ball detector, letterboxed to 544 x 960. The expected center is the label of the image.
     */
    private const FRAMES = [
        'gamelle1_00007.jpg' => [311.5, 532.2],
        'gamelle1_00020.jpg' => [330.1, 538.4],
        'goal1_00202.jpg' => [189.5, 462.1],
    ];

    public function testFindsTheBall(): void
    {
        $detector = static::getContainer()->get(BallDetector::class);

        foreach (self::FRAMES as $name => [$x, $y]) {
            $candidates = $detector->detect((string) file_get_contents(__DIR__.'/../Fixtures/'.$name));

            self::assertNotEmpty($candidates, $name);
            // The first candidate has the best confidence.
            self::assertEqualsWithDelta($x, $candidates[0]['center'][0], 3.0, $name);
            self::assertEqualsWithDelta($y, $candidates[0]['center'][1], 3.0, $name);
            self::assertGreaterThan(0.1, $candidates[0]['confidence'], $name);
        }
    }
}
