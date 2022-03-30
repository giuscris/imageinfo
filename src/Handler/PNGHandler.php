<?php

namespace ImageInfo\Handler;

use ImageInfo\ColorProfile\ColorProfile;
use ImageInfo\ColorProfile\ColorSpace;
use ImageInfo\EXIF\EXIFData;
use ImageInfo\Decoder\PNGDecoder;
use UnexpectedValueException;

class PNGHandler extends AbstractHandler
{
    public function getInfo(): array
    {
        $info = [
            'width'                => 0,
            'height'               => 0,
            'colorSpace'           => null,
            'colorDepth'           => null,
            'colorNumber'          => null,
            'alphaChannel'         => false,
            'animation'            => false,
            'animationFrames'      => null,
            'animationRepeatCount' => null
        ];

        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'IHDR') {
                $info['width'] = unpack('N', $chunk['value'], 0)[1];
                $info['height'] = unpack('N', $chunk['value'], 4)[1];
                $info['colorDepth'] = ord($chunk['value'][8]);
                [$info['colorSpace'], $info['alphaChannel']] = $this->getColorSpaceAndAlpha(ord($chunk['value'][9]));
            }

            if ($chunk['type'] === 'PLTE') {
                if ($chunk['size'] % 3 > 0) {
                    throw new UnexpectedValueException('Invalid palette size');
                }
                $info['colorNumber'] = $chunk['size'] / 3;
            }

            if ($chunk['type'] === 'acTL') {
                $info['animation'] = true;
                $info['animationFrames'] = unpack('N', $chunk['value'], 0)[1];
                $info['animationRepeatCount'] = unpack('N', $chunk['value'], 4)[1];
            }
        }

        return $info;
    }

    public function hasColorProfile(): bool
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'iCCP') {
                return true;
            }
        }

        return false;
    }

    public function getColorProfile(): ?ColorProfile
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'iCCP') {
                $profile = $this->decodeProfile($chunk['value']);
                return new ColorProfile($profile['value']);
            }
        }

        return null;
    }

    public function setColorProfile(ColorProfile $profile): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'IHDR') {
                $iCCPChunk = $this->encodeChunk('iCCP', $this->encodeProfile($profile->name(), $profile->getData()));
                $this->data = substr_replace($this->data, $iCCPChunk, $chunk['position'], 0);
                break;
            }
        }
    }

    public function removeColorProfile(): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'iCCP') {
                $this->data = substr_replace($this->data, '', $chunk['offset'], $chunk['position'] - $chunk['offset']);
                $chunk['position'] = $chunk['offset'];
                break;
            }
        }
    }

    public function hasEXIFData(): bool
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'eXIf') {
                return true;
            }
        }

        return false;
    }

    public function getEXIFData(): ?EXIFData
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'eXIf') {
                return new EXIFData($chunk['value']);
            }
        }

        return null;
    }

    public function setEXIFData(EXIFData $data): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'IHDR') {
                $iCCPChunk = $this->encodeChunk('eXIf', $data->getData());
                $this->data = substr_replace($this->data, $iCCPChunk, $chunk['position'], 0);
                break;
            }
        }
    }

    public function removeEXIFData(): void
    {
        foreach ($this->decoder->decode($this->data) as $chunk) {
            if ($chunk['type'] === 'eXIf') {
                $this->data = substr_replace($this->data, '', $chunk['offset'], $chunk['position'] - $chunk['offset']);
                $chunk['position'] = $chunk['offset'];
                break;
            }
        }
    }

    protected function getColorSpaceAndAlpha(int $colorType): array
    {
        switch ($colorType) {
            case 0:
                return [ColorSpace::GRAYSCALE, false];
            case 2:
                return [ColorSpace::RGB, false];
            case 3:
                return [ColorSpace::PALETTE, false];
            case 4:
                return [ColorSpace::GRAYSCALE, true];
            case 6:
                return [ColorSpace::RGB, true];
            default:
                throw new UnexpectedValueException('Invalid color space');
        }
    }

    protected function encodeChunk(string $name, string $data): string
    {
        return pack('N', strlen($data)) . $name . $data . pack('N', crc32($name . $data));
    }

    protected function decodeProfile(string $data): array
    {
        $name = unpack('Z*', $data)[1];
        $value = gzuncompress(substr($data, strlen($name) + 2));
        return ['name' => $name, 'value' => $value];
    }

    protected function encodeProfile(string $name, string $value): string
    {
        return trim($name) . "\x0\x0" . gzcompress($value);
    }

    protected function getDecoder(): PNGDecoder
    {
        return new PNGDecoder();
    }
}
