<?php

namespace Yagamel\AI\Platform\Bridge\KServe;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Yagamel\AI\Platform\Bridge\KServe\Contract\ImageNormalizer;

final class Factory
{
    public static function createPlatform(string $baseUrl, HttpClientInterface $httpClient): Platform
    {
        return new Platform([new Provider(
            'kserve',
            [new ModelClient($httpClient, $baseUrl)],
            [new ResultConverter()],
            new ModelCatalog(),
            Contract::create([new ImageNormalizer()]),
        )]);
    }
}
