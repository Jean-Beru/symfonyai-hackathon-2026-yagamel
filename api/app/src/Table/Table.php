<?php

namespace App\Table;

/**
 * The zones of the table, in pixels of the frame: the two goals and the green field between them.
 */
final readonly class Table implements \JsonSerializable
{
    public const TOP = 'top';
    public const BOTTOM = 'bottom';

    public function __construct(
        public Box $top,
        public Box $bottom,
        public Box $field,
    ) {
    }

    /**
     * @param array{float, float} $point
     *
     * @return self::TOP|self::BOTTOM|null the goal that holds the point
     */
    public function goalAt(array $point): ?string
    {
        return match (true) {
            $this->top->contains($point) => self::TOP,
            $this->bottom->contains($point) => self::BOTTOM,
            default => null,
        };
    }

    /**
     * Whether the point is on the field or in a goal. The spare balls, the hands and the floor are out of them.
     *
     * @param array{float, float} $point
     */
    public function holds(array $point): bool
    {
        return $this->field->contains($point) || null !== $this->goalAt($point);
    }

    /**
     * @return array{top: Box, bottom: Box, field: Box}
     */
    public function jsonSerialize(): array
    {
        return [self::TOP => $this->top, self::BOTTOM => $this->bottom, 'field' => $this->field];
    }
}
