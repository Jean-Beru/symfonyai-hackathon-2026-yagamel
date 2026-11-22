<?php

namespace Yagamel\AI\Platform\Bridge\KServe;

use Symfony\AI\Platform\Bridge\HuggingFace\Output\DetectedObject;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;

/**
 * Reads an output tensor of N rows (x1, y1, x2, y2, score, class), as a JSON array, in the pixels of the input.
 *
 * @see https://kserve.github.io/website/docs/concepts/architecture/data-plane/v2-protocol#inference-response-json-object
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof ObjectDetection;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ObjectResult
    {
        $this->throwOnHttpError($result->getObject());

        try {
            $data = $result->getData();
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException('The server did not answer with JSON.', previous: $e);
        }

        $name = $options['output'] ?? 'detections';
        $output = null;
        foreach ($data['outputs'] ?? [] as $candidate) {
            if (\is_array($candidate) && ($candidate['name'] ?? null) === $name) {
                $output = $candidate;
            }
        }
        if (null === $output) {
            throw new RuntimeException(\sprintf('The answer has no output "%s".', $name));
        }

        $shape = $output['shape'] ?? null;
        $values = $output['data'] ?? null;
        if (!\is_array($shape) || !\in_array(\count($shape), [2, 3], true) || 6 !== end($shape) || !array_is_list($shape)
            || !\is_array($values) || !array_is_list($values) || \count($values) !== array_product($shape)
            || array_filter($values, static fn (mixed $value): bool => !\is_int($value) && !\is_float($value))) {
            throw new RuntimeException(\sprintf('The output "%s" must be a list of rows of 6 numbers (x1, y1, x2, y2, score, class).', $name));
        }

        $threshold = (float) ($options['threshold'] ?? 0.0);
        $labels = $options['labels'] ?? [];
        $objects = [];
        foreach (array_chunk($values, 6) as [$x1, $y1, $x2, $y2, $score, $class]) {
            if ($score <= $threshold) {
                continue;
            }

            $objects[] = new DetectedObject($labels[(int) $class] ?? (string) (int) $class, (float) $score, (float) $x1, (float) $y1, (float) $x2, (float) $y2);
        }

        return new ObjectResult(new ObjectDetectionResult($objects));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
