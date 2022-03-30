<?php

namespace ImageInfo\Handler;

use ImageInfo\ColorProfile\ColorProfile;
use ImageInfo\ColorProfile\ColorSpace;
use ImageInfo\EXIF\EXIFData;
use ImageInfo\Decoder\GIFDecoder;
use RuntimeException;

class GIFHandler extends AbstractHandler
{
    protected const NETSCAPE_EXT_HEADER = "!\xff\x0bNETSCAPE2.0";

    public function getInfo(): array
    {
        $info = [
            'width'                => 0,
            'height'               => 0,
            'colorSpace'           => ColorSpace::PALETTE,
            'colorDepth'           => 8,
            'colorNumber'          => null,
            'alphaChannel'         => false,
            'animation'            => false,
            'animationFrames'      => null,
            'animationRepeatCount' => null
        ];

        foreach ($this->decoder->decode($this->data) as $block) {
            if ($block['type'] === 'LSD') {
                $info['width'] = $block['desc']['width'];
                $info['height'] = $block['desc']['height'];
                $info['colorNumber'] = 2 ** ($block['desc']['colorres'] + 1);
            }

            if ($block['type'] === 'EXT' && $block['label'] === 0xf9) {
                $info['alphaChannel'] = ord($block['value'][3]) & 0x01 === 1;
                if (!$info['animation']) {
                    $info['animation'] = unpack('v', $block['value'], 4)[1] > 0;
                }
            }

            if ($block['type'] === 'EXT' && strpos($block['value'], self::NETSCAPE_EXT_HEADER) === 0) {
                $info['animationRepeatCount'] = unpack('v', $block['value'], 16)[1];
                if ($info['animationRepeatCount'] > 0) {
                    $info['animationRepeatCount']++;
                }
            }

            if ($block['type'] === 'IMG' && $info['animation']) {
                $info['animationFrames']++;
            }
        }

        return $info;
    }

    public function hasColorProfile(): bool
    {
        return false;
    }

    public function getColorProfile(): ?ColorProfile
    {
        throw new RuntimeException('GIF does not support color profiles');
    }

    public function setColorProfile(ColorProfile $profile): void
    {
        throw new RuntimeException('GIF does not support color profiles');
    }

    public function removeColorProfile(): void
    {
        throw new RuntimeException('GIF does not support color profiles');
    }

    public function hasEXIFData(): bool
    {
        return false;
    }

    public function getEXIFData(): ?EXIFData
    {
        throw new RuntimeException('GIF does not support EXIF data');
    }

    public function removeEXIFData(): void
    {
        throw new RuntimeException('GIF does not support EXIF data');
    }

    protected function getDecoder(): GIFDecoder
    {
        return new GIFDecoder();
    }
}
