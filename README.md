# 🌄 ImageInfo

**PHP library to handle metadata information (color profiles, EXIF, etc.) from JPEG, PNG, GIF and WebP images**

## Usage

### Getting metadata

```php
use ImageInfo\ImageInfo;

$info = new ImageInfo('path/to/image.jpg');

$width = $info->width();

$height = $info->height();

$colorSpace = $info->colorSpace(); // RGB, CMYK, GRAYSCALE, …

$colorDepth = $info->colorDepth(); // Color depth in bits per pixel

$colorNumber = $info->colorNumber(); // Color number of palette images

$hasAlphaChannel = $info->hasAlphaChannel();

$isAnimation = $info->isAnimation();

$animationFrames = $info->animationFrames(); // Animation frames count

$animationRepeatCount = $info->animationRepeatCount(); // Animation repeat count (0 for infinite)

/**
 * @var ?ImageInfo\ColorProfile\ColorProfile
 */
$colorProfile = $info->getColorProfile();

/**
 * @var ?ImageInfo\EXIF\EXIFData
 */
$exif = $info->getEXIFData();
```

## Altering image color profile

```php
use ImageInfo\ImageInfo;

use ImageInfo\ColorProfile\ColorProfile;

$info = new ImageInfo('path/to/image.jpg');

$profileData = file_get_contents('path/to/color-profile.icc');

$info->setColorProfile(new ColorProfile($profileData));

$info->save();
```
