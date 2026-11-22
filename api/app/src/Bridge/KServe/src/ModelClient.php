<?php

namespace Yagamel\AI\Platform\Bridge\KServe;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls the infer endpoint of the KServe v2 protocol with the image as a BYTES tensor in the binary extension.
 *
 * @see https://kserve.github.io/website/docs/concepts/architecture/data-plane/v2-protocol#inference-request-json-object
 * @see https://kserve.github.io/website/docs/concepts/architecture/data-plane/v2-protocol/binary-tensor-data-extension
 */
final readonly class ModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ObjectDetection;
    }

    /**
     * @param array{body: string} $payload
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $image = $payload['body'];

        $header = json_encode([
            'inputs' => [[
                'name' => $options['input'] ?? 'image',
                'shape' => [1],
                'datatype' => 'BYTES',
                // The 4 bytes of the length of the element are part of the size.
                'parameters' => ['binary_data_size' => \strlen($image) + 4],
            ]],
            'outputs' => [[
                'name' => $options['output'] ?? 'detections',
                'parameters' => ['binary_data' => false],
            ]],
        ], \JSON_THROW_ON_ERROR);

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/v2/models/%s/infer', rtrim($this->baseUrl, '/'), rawurlencode($model->getName())), [
            'headers' => [
                'Content-Type' => 'application/octet-stream',
                'Inference-Header-Content-Length' => (string) \strlen($header),
            ],
            // Each element of a BYTES tensor starts with its length on 4 bytes, little endian.
            'body' => $header.pack('V', \strlen($image)).$image,
        ]));
    }
}
