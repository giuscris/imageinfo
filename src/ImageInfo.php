<?php

namespace ImageInfo;

use ImageInfo\ColorProfile\ColorProfile;
use ImageInfo\EXIF\EXIFData;
use ImageInfo\Handler\AbstractHandler;
use ImageInfo\Handler\GIFHandler;
use ImageInfo\Handler\JPEGHandler;
use ImageInfo\Handler\PNGHandler;
use ImageInfo\Handler\WEBPHandler;
use RuntimeException;

class ImageInfo
{
    protected string $path;

    protected AbstractHandler $handler;

    protected array $info;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Get image width
     */
    public function width(): int
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['width'];
    }

    /**
     * Get image height
     */
    public function height(): int
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['height'];
    }

    /**
     * Get image color space (RGB, CMYK, GRAYSCALE, etc.)
     */
    public function colorSpace(): ?string
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['colorSpace'];
    }

    /**
     * Get image color depth (bits per pixel)
     */
    public function colorDepth(): int
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['colorDepth'];
    }

    /**
     * Get image color number (palette images only)
     */
    public function colorNumber(): ?int
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['colorNumber'];
    }

    /**
     * Return whether the image has an alpha channel
     */
    public function hasAlphaChannel(): bool
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['alphaChannel'];
    }

    /**
     * Return whether the image is animated
     */
    public function isAnimation(): bool
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['animation'];
    }

    /**
     * Get animation frames count
     */
    public function animationFrames(): ?int
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['animationFrames'];
    }

    /**
     * Get animation repeat count (0 for infinite)
     */
    public function animationRepeatCount(): ?int
    {
        if (!isset($this->info)) {
            $this->info = $this->getInfo();
        }
        return $this->info['animationRepeatCount'];
    }

    /**
     * Return whether the image has a color profile
     */
    public function hasColorProfile(): bool
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        return $this->handler->hasColorProfile();
    }

    /**
     * Get color profile
     *
     * @throws RuntimeException if the image has no color profile
     */
    public function getColorProfile(): ?ColorProfile
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        return $this->handler->getColorProfile();
    }

    /**
     * Set color profile
     *
     * @throws RuntimeException if the image has no color profile
     */
    public function setColorProfile(ColorProfile $profile): void
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        $this->handler->setColorProfile($profile);
    }

    /**
     * Remove color profile
     *
     * @throws RuntimeException if the image has no color profile
     */
    public function removeColorProfile(): void
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        $this->handler->removeColorProfile();
    }

    /**
     * Return whether the image has EXIF data
     */
    public function hasEXIFData(): bool
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        return $this->handler->hasEXIFData();
    }

    /**
     * Get EXIF data
     *
     * @throws RuntimeException if the image does not support EXIF data
     */
    public function getEXIFData(): ?EXIFData
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        return $this->handler->getEXIFData();
    }

    /**
     * Set EXIF data
     *
     * @throws RuntimeException if the image does not support EXIF data
     */
    public function setEXIFData(EXIFData $data): void
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        $this->handler->setEXIFData($data);
    }

    /**
     * Remove EXIF data
     *
     * @throws RuntimeException if the image does not support EXIF data
     */
    public function removeEXIFData(): void
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        $this->handler->removeEXIFData();
    }

    /**
     * Save image
     */
    public function save(): void
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        $this->handler->save();
    }

    /**
     * Save image in a different path
     */
    public function saveAs(string $path): void
    {
        if (!isset($this->handler)) {
            $this->handler = $this->getHandler();
        }
        $this->handler->saveAs($path);
    }

    /**
     * Get handler for the image according to its MIME type
     */
    protected function getHandler(): AbstractHandler
    {
        $info = getimagesize($this->path);

        if ($info === false) {
            throw new RuntimeException('Failed to get image info');
        }

        switch ($info['mime']) {
            case 'image/jpeg':
                return new JPEGHandler($this->path);
            case 'image/png':
                return new PNGHandler($this->path);
            case 'image/gif':
                return new GIFHandler($this->path);
            case 'image/webp':
                return new WEBPHandler($this->path);
            default:
                throw new RuntimeException('Unsupported image type');
        }
    }

    /**
     * Get image info as an array
     */
    protected function getInfo(): array
    {
        if (!isset($this->handler)) {
            $this->handler = $this->handler = $this->getHandler();
        }
        return $this->handler->getInfo();
    }
}
