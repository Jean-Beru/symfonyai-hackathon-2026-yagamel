<?php

namespace App\Referee;

use App\Table\Table;

final readonly class Decision implements \JsonSerializable
{
    /**
     * @param Table::TOP|Table::BOTTOM $goal
     */
    public function __construct(
        public Call $call,
        public string $goal,
    ) {
    }

    /**
     * @return array{call: Call, goal: string}
     */
    public function jsonSerialize(): array
    {
        return ['call' => $this->call, 'goal' => $this->goal];
    }
}
