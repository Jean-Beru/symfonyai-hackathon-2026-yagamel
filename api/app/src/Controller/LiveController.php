<?php

namespace App\Controller;

use App\Viewer\FramePublisher;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The live page of the videos sent to POST /frames. Caddy serves their frames (frankenphp/Caddyfile).
 */
final readonly class LiveController
{
    /**
     * @return array<string, mixed>
     */
    #[Route('/live', methods: ['GET'])]
    #[Template('live.html.twig')]
    public function page(
        #[Autowire(env: 'int:PATH_SECONDS')]
        int $pathSeconds,
    ): array {
        return ['topic' => FramePublisher::TOPIC, 'path_seconds' => $pathSeconds];
    }
}
