<?php

namespace App\Frame;

use App\Table\Table;
use App\Referee\Decision;

/**
 * What the API found in one frame.
 */
final readonly class Analysis implements \JsonSerializable
{
    /**
     * @param array{box: array{float, float, float, float}, center: array{float, float}, confidence: float}|null $ball
     */
    public function __construct(
        public string $videoId,
        public int $timestamp,
        public ?Table $table,
        public ?array $ball,
        public ?Decision $decision,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'video_id' => $this->videoId,
            'timestamp' => $this->timestamp,
            'table' => $this->table,
            'ball' => $this->ball,
            'decision' => $this->decision,
        ];
    }
}
