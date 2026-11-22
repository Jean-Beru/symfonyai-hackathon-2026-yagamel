<?php

namespace App\Http;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The fields of POST /frames, next to the JPEG file "image".
 */
final readonly class FrameMeta
{
    /**
     * Also a folder name (App\Frame\FrameStorage) and a cache key: no dot, no slash.
     */
    public const VIDEO_ID = '[A-Za-z0-9_-]{1,64}';

    public function __construct(
        #[SerializedName('video_id')]
        #[Assert\Regex('/^'.self::VIDEO_ID.'$/D')]
        public string $videoId,
        /** Milliseconds from the start of the video. */
        #[Assert\PositiveOrZero]
        public int $timestamp,
    ) {
    }
}
