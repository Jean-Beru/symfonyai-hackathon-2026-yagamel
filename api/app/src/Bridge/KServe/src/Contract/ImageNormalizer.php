<?php

namespace Yagamel\AI\Platform\Bridge\KServe\Contract;

use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Model;
use Yagamel\AI\Platform\Bridge\KServe\ObjectDetection;

/**
 * Gives the raw bytes of the image to the model client. The default normalizer makes a data URL for chat models.
 */
final class ImageNormalizer extends ModelContractNormalizer
{
    /**
     * @param Image $data
     *
     * @return array{body: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return ['body' => $data->asBinary()];
    }

    protected function supportedDataClass(): string
    {
        return Image::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof ObjectDetection;
    }
}
