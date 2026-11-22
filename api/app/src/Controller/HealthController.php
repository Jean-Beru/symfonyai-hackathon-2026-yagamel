<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The health check of Compose. It does not call YOLO.
 */
final class HealthController
{
    #[Route('/health', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response();
    }
}
