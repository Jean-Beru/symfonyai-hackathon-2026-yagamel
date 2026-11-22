<?php

namespace App\Detection;

use Symfony\AI\Platform\Bridge\HuggingFace\Output\DetectedObject;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Finds the ball in a JPEG frame with the trained YOLO model, through Symfony AI. The only code that knows the model.
 */
final readonly class BallDetector
{
    /**
     * The input of the model (yolo/models/ball-detector): App\Frame\Letterbox fits the frames to this size.
     */
    public const WIDTH = 544;
    public const HEIGHT = 960;

    private const MODEL = 'ball-detector';

    public function __construct(
        private PlatformInterface $platform,
        #[Autowire(env: 'float:BALL_MIN_CONFIDENCE')]
        private float $minConfidence,
        #[Autowire(env: 'int:BALL_MAX_CANDIDATES')]
        private int $maxCandidates,
    ) {
    }

    /**
     * The candidates of the frame, best confidence first. The box is (x1, y1, x2, y2), in pixels of the frame. The
     * confidence is the raw score of YOLO.
     *
     * @return list<array{box: array{float, float, float, float}, center: array{float, float}, confidence: float}>
     */
    public function detect(string $jpeg): array
    {
        $objects = $this->platform->invoke(self::MODEL, new Image($jpeg, 'image/jpeg'), ['threshold' => $this->minConfidence])
            ->asObject(ObjectDetectionResult::class)
            ->getObjects();
        usort($objects, static fn (DetectedObject $a, DetectedObject $b): int => $b->getScore() <=> $a->getScore());

        return array_map(static fn (DetectedObject $object): array => [
            'box' => [$object->getXmin(), $object->getYmin(), $object->getXmax(), $object->getYmax()],
            'center' => [($object->getXmin() + $object->getXmax()) / 2, ($object->getYmin() + $object->getYmax()) / 2],
            'confidence' => $object->getScore(),
        ], \array_slice($objects, 0, $this->maxCandidates));
    }
}
