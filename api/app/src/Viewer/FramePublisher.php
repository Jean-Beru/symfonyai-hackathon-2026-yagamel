<?php

namespace App\Viewer;

use App\Frame\Analysis;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Sends the analysis of each frame to the live page, through the Mercure hub of FrankenPHP, with the URL of the frame:
 * Caddy serves var/videos/ on /videos/ (frankenphp/Caddyfile). The page keeps the path of the ball: the API sends one
 * frame at a time.
 */
final readonly class FramePublisher
{
    public const TOPIC = 'videos';

    public function __construct(
        private HubInterface $hub,
    ) {
    }

    public function publish(Analysis $analysis, int $processingMs): void
    {
        $this->hub->publish(new Update(self::TOPIC, json_encode([
            ...$analysis->jsonSerialize(),
            'image' => \sprintf('/videos/%s/%d.jpeg', $analysis->videoId, $analysis->timestamp),
            'processing_ms' => $processingMs,
        ], \JSON_THROW_ON_ERROR)));
    }
}
