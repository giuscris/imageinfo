<?php

namespace ImageInfo\Handler;

use ImageInfo\ColorProfile\ColorProfile;
use ImageInfo\ColorProfile\ColorSpace;
use ImageInfo\EXIF\EXIFData;
use ImageInfo\Decoder\WEBPDecoder;

class WEBPHandler extends AbstractHandler
{
    public function getInfo(): array
    {
        $info = [
            'width'                => 0,
            'height'               => 0,
            'colorSpace'           => ColorSpace::RGB,
            'colorDepth'           => 8,
            'colorNumber'          => null,
            'alphaChannel'         => false,
            'animation'            => false,
            'animationFrames'      => null,
            'animationRepeatCount' => null
        ];

        foreach ($this->decoder->decode($this->data) as $chunk) {
            switch ($chunk['type']) {
                case 'VP8X':
                    $info['alphaChannel'] = ((ord($chunk['value'][0]) >> 4) & 0x01) === 1;
                    $info['width'] = unpack('V', substr($chunk['value'], 4, 3) . "\x00")[1] + 1;
                    $info['height'] = unpack('V', substr($chunk['value'], 7, 3) . "\x00")[1] + 1;
                    break 2;
                case 'VP8 ':
                    $info['width'] = unpack('v', $chunk['value'], 6)[1] & 0x3fff;
                    $info['height'] = unpack('v', $chunk['value'], 8)[1] & 0x3fff;
                    break 2;
                case 'VP8L':
                    $bits = unpack('V', $chunk['value'], 1)[1];
                    $info['width'] = ($bits & 0x3fff) + 1;
                    $info['height'] = (($bits >> 14) & 0x3fff) + 1;
                    $info['alphaChannel'] = (($bits >> 28) & 0x01) === 1;
                    break 2;
            }
        }

        return $info;
    }

    public function hasColorProfile(): bool
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'ICCP') {
                return true;
            }
        }

        return false;
    }

    public function getColorProfile(): ?ColorProfile
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'ICCP') {
                return new ColorProfile($chunk['value']);
            }
        }

        return null;
    }

    public function setColorProfile(ColorProfile $profile): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if (in_array($chunk['type'], ['VP8X', 'VP8 ', 'VP8L'], true)) {
                $ICCPChunk = $this->encodeChunk('ICCP', $profile->getData());
                $this->data = substr_replace($this->data, $ICCPChunk, $chunk['position'], 0);
                break;
            }
        }
    }

    public function removeColorProfile(): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'ICCP') {
                $this->data = substr_replace($this->data, '', $chunk['offset'], $chunk['position'] - $chunk['offset']);
                $chunk['position'] = $chunk['offset'];
                break;
            }
        }
    }

    public function hasEXIFData(): bool
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'EXIF') {
                return true;
            }
        }

        return false;
    }

    public function getEXIFData(): ?EXIFData
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'EXIF') {
                return new EXIFData($chunk['value']);
            }
        }

        return null;
    }

    public function setEXIFData(EXIFData $data): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if (in_array($chunk['type'], ['VP8X', 'VP8 ', 'VP8L'], true)) {
                $EXIFChunk = $this->encodeChunk('EXIF', $data->getData());
                $this->data = substr_replace($this->data, $EXIFChunk, $chunk['position'], 0);
                break;
            }
        }
    }

    public function removeEXIFData(): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'EXIF') {
                $this->data = substr_replace($this->data, '', $chunk['offset'], $chunk['position'] - $chunk['offset']);
                $chunk['position'] = $chunk['offset'];
                break;
            }
        }
    }

    protected function encodeChunk(string $type, string $data): string
    {
        $data = strlen($data) !== 0 ? $data . "\x00" : $data;
        return $type . pack('V', strlen($data)) . $data;
    }

    protected function getDecoder(): WEBPDecoder
    {
        return new WEBPDecoder();
    }
}
