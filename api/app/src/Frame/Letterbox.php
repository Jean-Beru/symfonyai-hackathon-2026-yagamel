<?php

namespace App\Frame;

use App\Detection\BallDetector;

/**
 * Fits a frame of any size into the input of the model: scaled, centered, with gray bands around, as in the training.
 */
final readonly class Letterbox
{
    private const GRAY = 114;
    private const JPEG_QUALITY = 95;

    public function fit(string $jpeg): string
    {
        $source = imagecreatefromstring($jpeg) ?: throw new \InvalidArgumentException('The frame is not an image.');
        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(BallDetector::WIDTH / $width, BallDetector::HEIGHT / $height);
        [$scaledWidth, $scaledHeight] = [(int) round($width * $scale), (int) round($height * $scale)];

        // Bilinear, as cv2.resize in the training, and 4 times faster than imagecopyresampled on a 1080 x 1920 frame.
        imagesetinterpolation($source, \IMG_BILINEAR_FIXED);
        $scaled = imagescale($source, $scaledWidth, $scaledHeight) ?: throw new \InvalidArgumentException('The frame cannot be scaled.');

        $image = imagecreatetruecolor(BallDetector::WIDTH, BallDetector::HEIGHT);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, self::GRAY, self::GRAY, self::GRAY));
        imagecopy($image, $scaled, intdiv(BallDetector::WIDTH - $scaledWidth, 2), intdiv(BallDetector::HEIGHT - $scaledHeight, 2), 0, 0, $scaledWidth, $scaledHeight);

        $stream = fopen('php://memory', 'w+') ?: throw new \RuntimeException('No memory stream.');
        imagejpeg($image, $stream, self::JPEG_QUALITY);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
