# KServe platform bridge for Symfony AI

A platform bridge for `symfony/ai-platform` 0.15.0. It calls a server that speaks the
[KServe v2 inference protocol](https://kserve.github.io/website/docs/concepts/architecture/data-plane/v2-protocol):
OpenVINO Model Server, Triton, KServe and Seldon. It returns the object detection result of the Hugging Face bridge.

Namespace: `Yagamel\AI\Platform\Bridge\KServe`. The code names no application. The goal is a contribution to
`symfony/ai` later.

```php
$platform = Factory::createPlatform('http://ovms:8000', $httpClient);

$result = $platform->invoke('ball-detector', Image::fromFile('frame.jpg'), [
    'labels' => [0 => 'sports ball'],
    'threshold' => 0.25,
])->asObject(); // Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult
```

## How it works

- `Contract\ImageNormalizer`: gives the raw bytes of the `Image` to the client (the default normalizer makes a data
  URL).
- `ModelClient`: `POST {baseUrl}/v2/models/{name}/infer`. The body is built as a string: the JSON header (one input,
  `datatype` `BYTES`, `shape` `[1]`, `parameters.binary_data_size`), then the element of the tensor. The element starts
  with its length on 4 bytes, little endian, and `binary_data_size` counts these 4 bytes. The header
  `Inference-Header-Content-Length` is the length of the JSON. The output is asked as JSON.
- `ModelCatalog`: any name is a model of the server (`ObjectDetection`, capability `INPUT_IMAGE`). Options are in
  the table below.
- `ResultConverter`: reads the output `N x 6` or `1 x N x 6` (x1, y1, x2, y2, score, class), in the pixels of the
  input. It keeps the rows with a score above the threshold. Another shape, a missing output or an answer that is not
  JSON throws `Symfony\AI\Platform\Exception\RuntimeException`. HTTP errors become the exceptions of the platform
  (`ServerException` for a 5xx).

| Option      | Default      | Meaning                                                                           |
|-------------|--------------|-----------------------------------------------------------------------------------|
| `input`     | `image`      | Name of the input tensor.                                                         |
| `output`    | `detections` | Name of the output tensor.                                                        |
| `labels`    | `[]`         | Label of each class index. A class without label gets its index as label.         |
| `threshold` | `0.0`        | The rows with a score at or below it are dropped. `0.0` drops the rows of zeros.  |

## Known limits

- The server must decode the image: OVMS does for a `BYTES` input, Triton does not (it needs a model that decodes).
- The model must do its own NMS and give the boxes in the pixels of its input: this bridge holds no model code. The
  server resizes an image of another size without letterbox: send the size of the model.
- Object detection only. One image per request, the model has a batch of 1. The calls of several images overlap if the
  caller starts all `invoke()` calls before it reads the results.
- The output must be a JSON array (`binary_data` false). A binary output is not read.
- Depends on `symfony/ai-hugging-face-platform` for `ObjectDetectionResult` and `DetectedObject`. Upstream would need
  these classes in `symfony/ai-platform` first.
- Symfony AI is at 0.x: the version is pinned and the tests of the bridge run with the application.
