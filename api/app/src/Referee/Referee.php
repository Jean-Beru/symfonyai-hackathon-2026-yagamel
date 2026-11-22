<?php

namespace App\Referee;

use App\Table\Table;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Decides a goal or a gamelle from the position of the ball, frame after frame. When the ball is in a goal:
 * - no ball for GOAL_MISSING_FRAMES frames in a row: a goal;
 * - the ball out of the goal for GAMELLE_OUTSIDE_FRAMES frames: a gamelle.
 * The ball is also in a goal when its next position, at the same speed, is in the goal: the model often sees a fast
 * ball last just in front of the goal, and hardly sees it on the blue of the goal.
 * The frames of a video must come in order. The state of each video stays in the cache between the requests: the
 * workers of FrankenPHP share no memory.
 */
final readonly class Referee
{
    private const NO_BALL_IN_GOAL = ['goal' => null, 'missing' => 0, 'outside' => 0, 'last' => null];

    public function __construct(
        #[Target('referee.cache')]
        private CacheItemPoolInterface $cache,
        #[Autowire(env: 'int:GOAL_MISSING_FRAMES')]
        private int $goalAfter,
        #[Autowire(env: 'int:GAMELLE_OUTSIDE_FRAMES')]
        private int $gamelleAfter,
    ) {
    }

    /**
     * @param array{float, float}|null $ball the center of the ball, null when the frame has no ball
     */
    public function follow(string $videoId, ?array $ball, Table $table): ?Decision
    {
        $item = $this->cache->getItem('track.'.$videoId);
        /** @var array{goal: Table::TOP|Table::BOTTOM|null, missing: int, outside: int, last: array{float, float}|null} $track the ball of the previous frame in "last" */
        $track = $item->isHit() ? $item->get() : self::NO_BALL_IN_GOAL;
        $decision = null;

        $goal = null;
        if (null !== $ball) {
            $next = null === $track['last'] ? null : [2 * $ball[0] - $track['last'][0], 2 * $ball[1] - $track['last'][1]];
            $goal = $table->goalAt($ball) ?? (null === $next ? null : $table->goalAt($next));
        }
        if (null !== $goal) {
            $track = ['goal' => $goal] + self::NO_BALL_IN_GOAL;
        } elseif (null !== $track['goal']) {
            if (null === $ball) {
                ++$track['missing'];
            } else {
                $track['missing'] = 0;
                ++$track['outside'];
            }

            $call = match (true) {
                $track['missing'] >= $this->goalAfter => Call::Goal,
                $track['outside'] >= $this->gamelleAfter => Call::Gamelle,
                default => null,
            };
            if (null !== $call) {
                $decision = new Decision($call, $track['goal']);
                $track = self::NO_BALL_IN_GOAL;
            }
        }

        $track['last'] = $ball;
        $this->cache->save($item->set($track));

        return $decision;
    }
}
