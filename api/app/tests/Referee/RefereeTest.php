<?php

namespace App\Tests\Referee;

use App\Table\Box;
use App\Table\Table;
use App\Referee\Call;
use App\Referee\Decision;
use App\Referee\Referee;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RefereeTest extends TestCase
{
    private const IN_TOP_GOAL = [50.0, 10.0];
    private const IN_BOTTOM_GOAL = [50.0, 590.0];
    private const ON_THE_FIELD = [50.0, 400.0];

    public function testGoalWhenTheBallStaysAwayAfterTheGoal(): void
    {
        self::assertEquals([null, null, null, new Decision(Call::Goal, Table::TOP), null], $this->follow([self::IN_TOP_GOAL, null, null, null, null]));
    }

    public function testGamelleWhenTheBallComesBackOnTheField(): void
    {
        self::assertEquals([null, null, null, new Decision(Call::Gamelle, Table::BOTTOM)], $this->follow([self::IN_BOTTOM_GOAL, self::ON_THE_FIELD, null, self::ON_THE_FIELD]));
    }

    public function testAFastBallSeenLastInFrontOfTheGoal(): void
    {
        // 30 px per frame to the top goal (y 0 to 20): the next position (y 10) is in the goal.
        self::assertEquals([null, null, null, null, new Decision(Call::Goal, Table::TOP)], $this->follow([[50.0, 70.0], [50.0, 40.0], null, null, null]));
    }

    public function testASlowBallInFrontOfTheGoalIsNotInIt(): void
    {
        self::assertSame([null, null, null, null, null], $this->follow([[50.0, 45.0], [50.0, 40.0], null, null, null]));
    }

    public function testTheBallBackInTheGoalStartsAgain(): void
    {
        self::assertSame([null, null, null, null, null], $this->follow([self::IN_TOP_GOAL, null, null, self::IN_TOP_GOAL, null]));
    }

    public function testNoDecisionWithoutTheBallInAGoal(): void
    {
        self::assertSame([null, null, null, null, null], $this->follow([self::ON_THE_FIELD, null, null, null, self::ON_THE_FIELD]));
    }

    public function testEachVideoHasItsOwnState(): void
    {
        $referee = new Referee(new ArrayAdapter(), 1, 1);
        $table = $this->table();

        $referee->follow('a', self::IN_TOP_GOAL, $table);

        self::assertNull($referee->follow('b', null, $table));
        self::assertEquals(new Decision(Call::Goal, Table::TOP), $referee->follow('a', null, $table));
    }

    /**
     * @param list<array{float, float}|null> $balls
     *
     * @return list<Decision|null>
     */
    private function follow(array $balls): array
    {
        // A goal after 3 frames without the ball, a gamelle after 2 frames out of the goal.
        $referee = new Referee(new ArrayAdapter(), 3, 2);

        return array_map(fn (?array $ball): ?Decision => $referee->follow('video', $ball, $this->table()), $balls);
    }

    private function table(): Table
    {
        return new Table(new Box(0, 0, 100, 20), new Box(0, 580, 100, 600), new Box(0, 20, 100, 580));
    }
}
