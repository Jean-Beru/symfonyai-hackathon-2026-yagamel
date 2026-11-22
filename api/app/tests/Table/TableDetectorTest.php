<?php

namespace App\Tests\Table;

use App\Table\Box;
use App\Table\TableDetector;
use PHPUnit\Framework\TestCase;

final class TableDetectorTest extends TestCase
{
    public function testFindsTheGoalsAndTheField(): void
    {
        // The first frame of goal1.mov, letterboxed: the goals are the dark blue boxes at both ends of the
        // table, the field is the green between them.
        $table = (new TableDetector())->detect((string) file_get_contents(__DIR__.'/../Fixtures/goal1_00000.jpg'));

        self::assertEquals(new Box(224, 84, 328, 112), $table?->top);
        self::assertEquals(new Box(232, 712, 332, 752), $table?->bottom);
        self::assertEquals(new Box(108, 120, 448, 716), $table?->field);
    }

    public function testNoTableWithoutBlue(): void
    {
        $image = imagecreatetruecolor(544, 960);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 40, 140, 80));
        ob_start();
        imagejpeg($image);

        self::assertNull((new TableDetector())->detect((string) ob_get_clean()));
    }
}
