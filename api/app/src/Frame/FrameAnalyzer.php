<?php

namespace App\Frame;

use App\Detection\BallDetector;
use App\Table\VideoTable;
use App\Referee\Referee;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The steps for one frame of a video: letterbox, storage, table (once per video), ball, decision.
 */
final readonly class FrameAnalyzer
{
    public function __construct(
        private Letterbox $letterbox,
        private FrameStorage $storage,
        private VideoTable $videoTable,
        private BallDetector $ballDetector,
        private Referee $referee,
        #[Autowire(env: 'float:REFEREE_MIN_CONFIDENCE')]
        private float $minConfidence,
    ) {
    }

    public function analyze(string $videoId, int $timestamp, string $jpeg): Analysis
    {
        $jpeg = $this->letterbox->fit($jpeg);
        $this->storage->save($videoId, $timestamp, $jpeg);

        $table = $this->videoTable->get($videoId, $jpeg);

        $candidates = $this->ballDetector->detect($jpeg);
        $ball = array_find($candidates, fn (array $candidate): bool => $candidate['confidence'] >= $this->minConfidence && (null === $table || $table->holds($candidate['center'])));

        $decision = null === $table ? null : $this->referee->follow($videoId, $ball['center'] ?? null, $table);

        return new Analysis($videoId, $timestamp, $table, $ball, $decision);
    }
}
