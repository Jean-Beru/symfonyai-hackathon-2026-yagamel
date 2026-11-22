<?php

namespace App\Frame;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Keeps each frame, as sent to the model: <directory>/<video id>/<timestamp>.jpeg.
 */
final readonly class FrameStorage
{
    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/videos')]
        private string $directory,
    ) {
    }

    public function save(string $videoId, int $timestamp, string $jpeg): void
    {
        $this->filesystem->dumpFile($this->path($videoId, $timestamp), $jpeg);
    }

    /**
     * The video id must match App\Http\FrameMeta::VIDEO_ID: no path out of the directory.
     */
    public function path(string $videoId, int $timestamp): string
    {
        return \sprintf('%s/%s/%d.jpeg', $this->directory, $videoId, $timestamp);
    }
}
