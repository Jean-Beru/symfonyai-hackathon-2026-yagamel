<?php

namespace Yagamel\AI\Platform\Bridge\KServe;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * The server knows its models: any name is an object detection model of the server.
 */
final class ModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        if ('' === $modelName) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $parsed = $this->parseModelName($modelName);

        return new ObjectDetection($parsed['name'], [Capability::INPUT_IMAGE], $parsed['options']);
    }
}
