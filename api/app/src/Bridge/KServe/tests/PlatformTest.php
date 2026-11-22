<?php

namespace Yagamel\AI\Platform\Bridge\KServe\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Yagamel\AI\Platform\Bridge\KServe\Factory;

final class PlatformTest extends TestCase
{
    private const OPTIONS = ['labels' => [0 => 'sports ball'], 'threshold' => 0.25];

    /** @var array<string, mixed> */
    private array $request = [];

    public function testRequestBody(): void
    {
        $this->detect($this->answer([1, 10, 6], [...[10, 20, 30, 50, 0.9, 0], ...array_fill(0, 54, 0)]), 'jpeg-bytes');

        self::assertSame('POST', $this->request['method']);
        self::assertSame('http://ovms:8000/v2/models/ball-detector/infer', $this->request['url']);

        $body = $this->request['body'];
        $headerLength = (int) $this->request['headers']['inference-header-content-length'];
        $header = json_decode(substr($body, 0, $headerLength), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([[
            'name' => 'image',
            'shape' => [1],
            'datatype' => 'BYTES',
            'parameters' => ['binary_data_size' => 14],
        ]], $header['inputs']);
        self::assertSame('detections', $header['outputs'][0]['name']);
        // The length of the element (4 bytes, little endian), then the bytes.
        self::assertSame("\x0a\x00\x00\x00jpeg-bytes", substr($body, $headerLength));
    }

    public function testConversion(): void
    {
        $result = $this->detect($this->answer([1, 10, 6], [
            ...[10.5, 20, 30.5, 50, 0.9, 0],
            ...[1, 2, 3, 4, 0.5, 0],
            ...array_fill(0, 48, 0),
        ]));

        self::assertInstanceOf(ObjectDetectionResult::class, $result);
        self::assertCount(2, $result->getObjects());
        [$first, $second] = $result->getObjects();
        self::assertSame('sports ball', $first->getLabel());
        self::assertSame(0.9, $first->getScore());
        self::assertSame([10.5, 20.0, 30.5, 50.0], [$first->getXmin(), $first->getYmin(), $first->getXmax(), $first->getYmax()]);
        self::assertSame(0.5, $second->getScore());
    }

    public function testThresholdDropsLowScores(): void
    {
        $result = $this->detect($this->answer([1, 10, 6], [
            ...[10, 20, 30, 50, 0.9, 0],
            ...[1, 2, 3, 4, 0.25, 0],
            ...[1, 2, 3, 4, 0.1, 0],
            ...array_fill(0, 42, 0),
        ]));

        self::assertCount(1, $result->getObjects());
    }

    public function testEmptyOutput(): void
    {
        self::assertSame([], $this->detect($this->answer([1, 10, 6], array_fill(0, 60, 0)))->getObjects());
    }

    public function testUnknownClassGetsItsIndexAsLabel(): void
    {
        $result = $this->detect($this->answer([10, 6], [...[1, 2, 3, 4, 0.9, 3], ...array_fill(0, 54, 0)]));

        self::assertSame('3', $result->getObjects()[0]->getLabel());
    }

    public function testWrongShape(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rows of 6 numbers');

        $this->detect($this->answer([1, 10, 5], array_fill(0, 50, 0)));
    }

    public function testDataDoesNotMatchShape(): void
    {
        $this->expectException(RuntimeException::class);

        $this->detect($this->answer([1, 10, 6], array_fill(0, 12, 0)));
    }

    public function testMissingOutput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no output "detections"');

        $this->detect(new MockResponse(json_encode(['outputs' => []])));
    }

    public function testNotJson(): void
    {
        $this->expectException(RuntimeException::class);

        $this->detect(new MockResponse('nope'));
    }

    public function testServerError(): void
    {
        $this->expectException(ServerException::class);

        $this->detect(new MockResponse('{"error":"boom"}', ['http_code' => 500]));
    }

    /**
     * @param list<int>   $shape
     * @param list<float> $data
     */
    private function answer(array $shape, array $data): MockResponse
    {
        return new MockResponse(json_encode(['model_name' => 'ball-detector', 'outputs' => [['name' => 'detections', 'datatype' => 'FP32', 'shape' => $shape, 'data' => $data]]]));
    }

    private function detect(MockResponse $response, string $image = 'jpeg'): ObjectDetectionResult
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->request = ['method' => $method, 'url' => $url, 'body' => $options['body'], 'headers' => array_change_key_case($this->headers($options['headers']))];

            return $response;
        });

        $platform = Factory::createPlatform('http://ovms:8000', $client);
        self::assertInstanceOf(Platform::class, $platform);

        $result = $platform->invoke('ball-detector', new Image($image, 'image/jpeg'), self::OPTIONS)->asObject();
        self::assertInstanceOf(ObjectDetectionResult::class, $result);

        return $result;
    }

    /**
     * @param list<string> $lines
     *
     * @return array<string, string>
     */
    private function headers(array $lines): array
    {
        $headers = [];
        foreach ($lines as $line) {
            [$name, $value] = explode(': ', $line, 2);
            $headers[$name] = $value;
        }

        return $headers;
    }
}
