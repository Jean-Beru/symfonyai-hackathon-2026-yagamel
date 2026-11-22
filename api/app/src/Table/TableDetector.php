<?php

namespace App\Table;

/**
 * Finds the zones of the table in a frame by their color:
 * - the goals: the largest blue blob in the top third, and in the bottom third. The blue players and the blue score
 *   marks are smaller;
 * - the field: the rows and the columns that are green on a quarter of their length at least. The rods and the
 *   players cut the green in bands, and a green spot out of the table does not fill a quarter of a row.
 */
final readonly class TableDetector
{
    /**
     * HSV ranges of the blue goals, on the scale of OpenCV: hue 0 to 180, saturation and value 0 to 255 (Yagamel
     * referee/app/detect.py).
     */
    private const BLUE = [
        [[90, 80, 50], [120, 100, 170]],
        [[100, 150, 50], [140, 255, 200]],
        [[100, 120, 10], [140, 255, 100]],
    ];

    /**
     * HSV range of the green field, on the same scale. The light on the field makes the saturation low: 37 to 125
     * on the three videos. The gray floor has a hue of about 111.
     */
    private const GREEN = [[[40, 30, 50], [90, 255, 255]]];

    /**
     * The frame is shrunk by this factor before the scan: 16 times fewer pixels, and smoother colors.
     */
    private const SHRINK = 4;

    /**
     * A goal is about 25 x 8 cells of the shrunk frame: a smaller blob is not a goal.
     */
    private const MIN_CELLS = 50;

    public function detect(string $jpeg): ?Table
    {
        $frame = imagecreatefromstring($jpeg) ?: throw new \InvalidArgumentException('The frame is not an image.');
        [$width, $height] = [intdiv(imagesx($frame), self::SHRINK), intdiv(imagesy($frame), self::SHRINK)];
        $small = imagecreatetruecolor($width, $height);
        imagecopyresampled($small, $frame, 0, 0, 0, 0, $width, $height, imagesx($frame), imagesy($frame));

        $blue = [];
        $greenRows = array_fill(0, $height, 0);
        $greenColumns = array_fill(0, $width, 0);
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $hsv = $this->hsv(imagecolorat($small, $x, $y));
                if ($this->matches($hsv, self::BLUE)) {
                    $blue[$y * $width + $x] = true;
                } elseif ($this->matches($hsv, self::GREEN)) {
                    ++$greenRows[$y];
                    ++$greenColumns[$x];
                }
            }
        }

        $top = $this->largestBlob($blue, $width, 0, intdiv($height, 3));
        $bottom = $this->largestBlob($blue, $width, $height - intdiv($height, 3), $height);
        $field = $this->field($greenRows, $greenColumns);

        return null === $top || null === $bottom || null === $field ? null : new Table($top, $bottom, $field);
    }

    /**
     * @return array{float, float, int} hue, saturation and value, on the scale of OpenCV
     */
    private function hsv(int $rgb): array
    {
        [$r, $g, $b] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
        $max = max($r, $g, $b);
        $delta = $max - min($r, $g, $b);
        $hue = match (true) {
            0 === $delta => 0,
            $max === $r => 60 * ($g - $b) / $delta,
            $max === $g => 120 + 60 * ($b - $r) / $delta,
            default => 240 + 60 * ($r - $g) / $delta,
        };

        return [($hue < 0 ? $hue + 360 : $hue) / 2, 0 === $max ? 0 : 255 * $delta / $max, $max];
    }

    /**
     * @param array{float, float, int}                                         $hsv
     * @param list<array{array{int, int, int}, array{int, int, int}}> $ranges
     */
    private function matches(array $hsv, array $ranges): bool
    {
        foreach ($ranges as [$low, $high]) {
            if ($hsv[0] >= $low[0] && $hsv[0] <= $high[0] && $hsv[1] >= $low[1] && $hsv[1] <= $high[1] && $hsv[2] >= $low[2] && $hsv[2] <= $high[2]) {
                return true;
            }
        }

        return false;
    }

    /**
     * The box of the rows and the columns that are green on a quarter of their length at least, in pixels of the frame.
     *
     * @param list<int> $rows    the number of green cells in each row
     * @param list<int> $columns the number of green cells in each column
     */
    private function field(array $rows, array $columns): ?Box
    {
        $greenRows = array_keys(array_filter($rows, fn (int $cells): bool => $cells >= \count($columns) / 4));
        $greenColumns = array_keys(array_filter($columns, fn (int $cells): bool => $cells >= \count($rows) / 4));
        if ([] === $greenRows || [] === $greenColumns) {
            return null;
        }

        return new Box(min($greenColumns) * self::SHRINK, min($greenRows) * self::SHRINK, (max($greenColumns) + 1) * self::SHRINK, (max($greenRows) + 1) * self::SHRINK);
    }

    /**
     * The bounding box of the largest blob of blue cells between the rows $from and $to, in pixels of the frame. A
     * flood fill joins the cells that touch on a side.
     *
     * @param array<int, true> $blue the blue cells, by y * width + x
     */
    private function largestBlob(array $blue, int $width, int $from, int $to): ?Box
    {
        $best = null;
        $bestCells = self::MIN_CELLS - 1;
        $seen = [];
        foreach (array_keys($blue) as $start) {
            if (isset($seen[$start]) || $start < $from * $width || $start >= $to * $width) {
                continue;
            }
            $seen[$start] = true;
            $stack = [$start];
            $cells = 0;
            [$x1, $y1, $x2, $y2] = [\PHP_INT_MAX, \PHP_INT_MAX, 0, 0];
            while (null !== ($cell = array_pop($stack))) {
                ++$cells;
                [$x, $y] = [$cell % $width, intdiv($cell, $width)];
                [$x1, $y1, $x2, $y2] = [min($x1, $x), min($y1, $y), max($x2, $x), max($y2, $y)];
                $neighbors = [$x > 0 ? $cell - 1 : null, $x < $width - 1 ? $cell + 1 : null, $y > $from ? $cell - $width : null, $y < $to - 1 ? $cell + $width : null];
                foreach ($neighbors as $neighbor) {
                    if (null !== $neighbor && isset($blue[$neighbor]) && !isset($seen[$neighbor])) {
                        $seen[$neighbor] = true;
                        $stack[] = $neighbor;
                    }
                }
            }
            if ($cells > $bestCells) {
                $bestCells = $cells;
                $best = new Box($x1 * self::SHRINK, $y1 * self::SHRINK, ($x2 + 1) * self::SHRINK, ($y2 + 1) * self::SHRINK);
            }
        }

        return $best;
    }
}
