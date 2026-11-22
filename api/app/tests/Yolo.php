<?php

namespace App\Tests;

use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Yagamel\AI\Platform\Bridge\KServe\Factory;

/**
 * The YOLO model server, mocked: answers in the format of the model (1 x 10 x 6, rows of zeros after the last box).
 */
final class Yolo
{
    /**
     * @param list<MockResponse|\Closure>|\Closure $responses
     */
    public static function platform(array|\Closure $responses): PlatformInterface
    {
        return Factory::createPlatform('http://yolo:8000', new MockHttpClient($responses));
    }

    /**
     * @param list<list<float|int>> $rows (x1, y1, x2, y2, score, class)
     */
    public static function answer(array $rows, ?\Closure $onRead = null): MockResponse
    {
        $rows = array_pad($rows, 10, [0, 0, 0, 0, 0, 0]);
        $body = json_encode(['outputs' => [['name' => 'detections', 'datatype' => 'FP32', 'shape' => [1, 10, 6], 'data' => array_merge(...$rows)]]]);

        return new MockResponse((static function () use ($body, $onRead): iterable {
            $onRead?->__invoke();

            yield $body;
        })());
    }
}
