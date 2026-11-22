<?php

namespace App\Tests\Frame;

use App\Frame\Letterbox;
use PHPUnit\Framework\TestCase;

final class LetterboxTest extends TestCase
{
    public function testFitsAPortraitFrameWithGrayBands(): void
    {
        $frame = imagecreatetruecolor(1080, 1920);
        imagefill($frame, 0, 0, (int) imagecolorallocate($frame, 255, 255, 255));
        ob_start();
        imagejpeg($frame);

        $image = imagecreatefromstring((new Letterbox())->fit((string) ob_get_clean()));

        self::assertNotFalse($image);
        self::assertSame([544, 960], [imagesx($image), imagesy($image)]);
        // 1080 x 1920 to 540 x 960: a band of 2 pixels on each side.
        self::assertEqualsWithDelta(114, imagecolorat($image, 0, 480) & 0xFF, 3);
        self::assertEqualsWithDelta(255, imagecolorat($image, 272, 480) & 0xFF, 3);
    }
}
