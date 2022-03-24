<?php

namespace ImageInfo\Handler;

use ImageInfo\ColorProfile\ColorProfile;
use ImageInfo\Decoder\DecoderInterface;
use ImageInfo\EXIF\EXIFData;

abstract class AbstractHandler
{
    protected string $path;

    protected string $data;

    protected DecoderInterface $decoder;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->data = file_get_contents($path);
        $this->decoder = $this->getDecoder();
    }

    /**
     * Get image info as an array
     */
    abstract public function getInfo(): array;

    /**
     * Return whether the image has a color profile
     */
    abstract public function hasColorProfile(): bool;

    /**
     * Get color profile
     *
     * @throws RuntimeException if the image has no color profile
     */
    abstract public function getColorProfile(): ?ColorProfile;

    /**
     * Set color profile
     *
     * @throws RuntimeException if the image has no color profile
     */
    abstract public function setColorProfile(ColorProfile $profile): void;

    /**
     * Remove color profile
     *
     * @throws RuntimeException if the image has no color profile
     */
    abstract public function removeColorProfile(): void;

    /**
     * Return whether the image has EXIF data
     */
    abstract public function hasEXIFData(): bool;

    /**
     * Get EXIF data
     *
     * @throws RuntimeException if the image has no EXIF data
     */
    abstract public function getEXIFData(): ?EXIFData;

    /**
     * Remove EXIF data
     *
     * @throws RuntimeException if the image has no EXIF data
     */
    abstract public function removeEXIFData(): void;

    /**
     * Save image
     */
    public function save(): void
    {
        file_put_contents($this->path, $this->data);
    }

    /**
     * Save image in a different path
     */
    public function saveAs(string $path): void
    {
        file_put_contents($path, $this->data);
    }

    /**
     * Get image decoder
     */
    abstract protected function getDecoder(): DecoderInterface;
}
