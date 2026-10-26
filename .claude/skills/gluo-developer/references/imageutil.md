# Image Manipulation: byjg/imageutil

`byjg/imageutil` is a fluent GD-based image processing library. It is **not installed by default**.

Install: `composer require "byjg/imageutil"` (requires PHP `ext-gd`)

---

## Loading an image

```php
use ByJG\ImageUtil\ImageUtil;
use ByJG\ImageUtil\Enum\FileType;
use ByJG\ImageUtil\Color;

// From a file on disk
$img = ImageUtil::fromFile('/path/to/photo.jpg');

// From an existing GD resource
$img = ImageUtil::fromResource($gdResource);

// Create blank canvas
$img = ImageUtil::empty(800, 600, FileType::PNG, Color::fromHex('#ffffff'));
```

---

## Resizing

All resize operations return `$this` (fluent interface), except methods that return a value.

```php
// Fixed dimensions — may distort
$img->resize(400, 300);

// Square crop (centered, fills gaps with $color)
$img->resizeSquare(200, Color::fromHex('#000000'));

// Fit within a bounding box, preserving aspect ratio (letterbox)
$img->resizeAspectRatio(800, 600, Color::fromHex('#ffffff'));
```

---

## Cropping and rotating

```php
// Crop a rectangle: (x1, y1, x2, y2)
$img->crop(50, 50, 350, 250);

// Rotate by degrees (counter-clockwise)
$img->rotate(90);
$img->rotate(45);   // arbitrary angle; fills corners with background color
```

---

## Flipping

```php
use ByJG\ImageUtil\Enum\Flip;

$img->flip(Flip::Horizontal);
$img->flip(Flip::Vertical);
$img->flip(Flip::Both);
```

---

## Stamping (watermark / overlay)

Stamp one image over another at a named position:

```php
use ByJG\ImageUtil\Enum\StampPosition;

$stamp = ImageUtil::fromFile('/assets/watermark.png');

$img->stampImage(
    $stamp,
    StampPosition::BottomRight,  // position
    10,                           // x padding from edge
    10,                           // y padding from edge
    70                            // opacity (0-100)
);
```

**`StampPosition` options:** `TopLeft`, `Top`, `TopRight`, `Left`, `Center`,
`Right`, `BottomLeft`, `Bottom`, `BottomRight`, `Random`

---

## Text overlay

```php
use ByJG\ImageUtil\Enum\TextAlignment;

$img->writeText(
    'Hello World',           // text
    [10, 50],                // [x, y] position
    24,                      // font size
    0,                       // angle (degrees)
    '/path/to/font.ttf',     // TrueType font path
    300,                     // max width (0 = no wrap)
    Color::fromHex('#ff0000'),
    TextAlignment::Left      // LEFT, CENTER, RIGHT
);
```

---

## Colors

```php
use ByJG\ImageUtil\Color;
use ByJG\ImageUtil\AlphaColor;

$red      = Color::fromHex('#FF0000');
$white    = Color::fromHex('#ffffff');

// With alpha channel (0 = fully opaque, 127 = fully transparent)
$semiRed  = new AlphaColor(255, 0, 0, 64);
```

---

## Saving and outputting

```php
// Save to file (format inferred from extension)
$img->save('/path/to/output.jpg', 85);  // 85 = JPEG quality (0-100)
$img->save('/path/to/output.png');      // PNG ignores quality

// Output directly to browser (sets Content-Type header)
$img->show();

// Restore to original state (undo all operations)
$img->restore();
```

**Supported formats:** JPEG, PNG, GIF, BMP, WebP, SVG

---

## Common patterns

### Resize uploaded image before saving

```php
use ByJG\ImageUtil\ImageUtil;
use ByJG\ImageUtil\Color;

$img = ImageUtil::fromFile($uploadedTmpPath);
$img->resizeAspectRatio(1200, 800, Color::fromHex('#ffffff'))
    ->save($storagePath . '/image.jpg', 80);
```

### Generate a thumbnail

```php
$img = ImageUtil::fromFile($originalPath);
$img->resizeSquare(150, Color::fromHex('#000000'))
    ->save($thumbPath . '/thumb.jpg', 70);
```

### Watermark a product image

```php
$stamp = ImageUtil::fromFile('/assets/logo.png');

$img = ImageUtil::fromFile($productImagePath);
$img->resizeAspectRatio(800, 600, Color::fromHex('#ffffff'))
    ->stampImage($stamp, StampPosition::BottomRight, 15, 15, 60)
    ->save($outputPath, 85);
```

### Create a social-sharing card

```php
$img = ImageUtil::empty(1200, 630, FileType::PNG, Color::fromHex('#1a1a2e'));
$img->writeText($title, [60, 300], 48, 0, '/fonts/Bold.ttf', 1080, Color::fromHex('#ffffff'), TextAlignment::Left)
    ->writeText($subtitle, [60, 380], 24, 0, '/fonts/Regular.ttf', 1080, Color::fromHex('#aaaaaa'), TextAlignment::Left)
    ->save('/tmp/social-card.png');
```

---

## Quick reference

| Goal | Code |
|---|---|
| Load file | `ImageUtil::fromFile($path)` |
| Blank canvas | `ImageUtil::empty($w, $h, FileType::PNG, $color)` |
| Resize (fixed) | `->resize($w, $h)` |
| Fit in box | `->resizeAspectRatio($w, $h, $bgColor)` |
| Square thumbnail | `->resizeSquare($size, $bgColor)` |
| Crop | `->crop($x1, $y1, $x2, $y2)` |
| Rotate | `->rotate($degrees)` |
| Flip | `->flip(Flip::Horizontal)` |
| Watermark | `->stampImage($stamp, StampPosition::BottomRight, $px, $py, $opacity)` |
| Write text | `->writeText($text, [$x,$y], $size, $angle, $font, $maxW, $color, TextAlignment::Left)` |
| Save | `->save($path, $quality)` |
| Output to browser | `->show()` |
| Color from hex | `Color::fromHex('#rrggbb')` |
| Color with alpha | `new AlphaColor($r, $g, $b, $alpha)` |