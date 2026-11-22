<?php

namespace App\Controller;

use App\Frame\FrameAnalyzer;
use App\Http\FrameMeta;
use App\Viewer\FramePublisher;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * One frame of a video, in order: multipart with the fields of FrameMeta and the JPEG file "image", of any size.
 */
final readonly class FrameController
{
    public function __construct(
        private FrameAnalyzer $analyzer,
        private FramePublisher $publisher,
    ) {
    }

    #[Route('/frames', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] FrameMeta $meta,
        #[MapUploadedFile(new Assert\Image(mimeTypes: ['image/jpeg']))] UploadedFile $image,
    ): JsonResponse {
        $start = hrtime(true);

        try {
            $analysis = $this->analyzer->analyze($meta->videoId, $meta->timestamp, $image->getContent());
        } catch (TimeoutExceptionInterface $e) {
            throw new HttpException(Response::HTTP_GATEWAY_TIMEOUT, 'YOLO did not answer in time.', $e);
        } catch (TransportExceptionInterface|ExceptionInterface $e) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'YOLO failed on the frame.', $e);
        }

        $processingMs = intdiv(hrtime(true) - $start, 1_000_000);
        $this->publisher->publish($analysis, $processingMs);

        return new JsonResponse([...$analysis->jsonSerialize(), 'processing_ms' => $processingMs]);
    }
}
