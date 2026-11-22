<?php

namespace App\Table;

/**
 * A rectangle in pixels of the frame: (x1, y1) top left, (x2, y2) bottom right.
 */
final readonly class Box implements \JsonSerializable
{
    public function __construct(
        public int $x1,
        public int $y1,
        public int $x2,
        public int $y2,
    ) {
    }

    /**
     * @param array{float, float} $point
     */
    public function contains(array $point): bool
    {
        return $point[0] >= $this->x1 && $point[0] <= $this->x2 && $point[1] >= $this->y1 && $point[1] <= $this->y2;
    }

    /**
     * @return array{int, int, int, int}
     */
    public function jsonSerialize(): array
    {
        return [$this->x1, $this->y1, $this->x2, $this->y2];
    }
}
