<?php

namespace Yagamel\AI\Platform\Bridge\KServe;

use Symfony\AI\Platform\Model;

/**
 * A detection model: one image in, one tensor of N boxes (x1, y1, x2, y2, score, class) out.
 *
 * Options:
 * - input: name of the input tensor, "image" by default
 * - output: name of the output tensor, "detections" by default
 * - labels: label of each class index, for example [0 => 'sports ball']. A class without label gets its index.
 * - threshold: the boxes with a score at or below it are dropped, 0.0 by default (drops the rows of zeros)
 */
final class ObjectDetection extends Model
{
}
