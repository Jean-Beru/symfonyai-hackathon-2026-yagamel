<?php

namespace App\Table;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The table of each video: detected on the first frame where the goals and the field are found, then read from the
 * cache.
 */
final readonly class VideoTable
{
    public function __construct(
        private TableDetector $detector,
        #[Target('referee.cache')]
        private CacheInterface $cache,
    ) {
    }

    public function get(string $videoId, string $jpeg): ?Table
    {
        return $this->cache->get('table.'.$videoId, function (ItemInterface $item, bool &$save) use ($jpeg): ?Table {
            $table = $this->detector->detect($jpeg);
            // Not found, a hand on a goal for example: try again on the next frame.
            $save = null !== $table;

            return $table;
        });
    }
}
