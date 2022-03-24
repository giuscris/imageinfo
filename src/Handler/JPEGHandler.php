<?php

namespace ImageInfo\Handler;

use ImageInfo\ColorProfile\ColorProfile;
use ImageInfo\ColorProfile\ColorSpace;
use ImageInfo\EXIF\EXIFData;
use ImageInfo\EXIF\EXIFReader;
use ImageInfo\Decoder\JPEGDecoder;
use UnexpectedValueException;

class JPEGHandler extends AbstractHandler
{
    protected const MAX_BYTES_IN_SEGMENT = 65533;

    protected const EXIF_HEADER = "Exif\x00\x00";

    protected const ICC_PROFILE_HEADER = "ICC_PROFILE\x00";

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

        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] > 0xbf && $segment['type'] < 0xc3
            || $segment['type'] > 0xc8 && $segment['type'] < 0xcc) {
                $info['colorDepth'] = ord($segment['value'][0]);
                $info['width'] = unpack('n', $segment['value'], 1)[1];
                $info['height'] = unpack('n', $segment['value'], 3)[1];
                $info['colorSpace'] = $this->getColorSpace(ord($segment['value'][5]));
                break;
            }
        }

        return $info;
    }

    public function hasColorProfile(): bool
    {
        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] === 0xe2 && strpos($segment['value'], self::ICC_PROFILE_HEADER) === 0) {
                return true;
            }
        }

        return false;
    }

    public function getColorProfile(): ?ColorProfile
    {
        $headerLength = strlen(self::ICC_PROFILE_HEADER);
        $profileChunks = [];
        $chunkCount = 0;

        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] === 0xe2 && strpos($segment['value'], self::ICC_PROFILE_HEADER) === 0) {
                [$chunkNum, $chunkCount] = array_values(unpack('Cnum/Ccount', $segment['value'], $headerLength));
                $profileChunks[$chunkNum] = substr($segment['value'], $headerLength + 2);
            }
        }

        if ($profileChunks === []) {
            return null;
        }

        if (count($profileChunks) !== $chunkCount) {
            throw new UnexpectedValueException('Unexpected profile chunk count');
        }

        ksort($profileChunks);
        return new ColorProfile(implode('', $profileChunks));
    }

    public function setColorProfile(ColorProfile $profile): void
    {
        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] === 0xd8) {
                $this->data = substr_replace($this->data, $this->encodeColorProfile($profile->getData()), $segment['position'], 0);
                break;
            }
        }
    }

    public function removeColorProfile(): void
    {
        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] === 0xe2 && strpos($segment['value'], self::ICC_PROFILE_HEADER) === 0) {
                $this->data = substr_replace($this->data, '', $segment['offset'], $segment['position'] - $segment['offset']);
                $segment['position'] = $segment['offset'];
            }
        }
    }

    public function hasEXIFData(): bool
    {
        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] === 0xe1 && strpos($segment['value'], self::EXIF_HEADER) === 0) {
                return true;
            }
        }
        return false;
    }

    public function getEXIFData(): ?EXIFData
    {
        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] === 0xe1 && strpos($segment['value'], self::EXIF_HEADER) === 0) {
                $exifData = substr($segment['value'], strlen(self::EXIF_HEADER));
                return new EXIFData(EXIFReader::fromString($exifData)->getData());
            }
        }
        return null;
    }

    public function removeEXIFData(): void
    {
        foreach ($this->decoder->decode($this->data) as $segment) {
            if ($segment['type'] === 0xe1 && strpos($segment['value'], self::EXIF_HEADER) === 0) {
                $this->data = substr_replace($this->data, '', $segment['offset'], $segment['position'] - $segment['offset']);
                $segment['position'] = $segment['offset'];
            }
        }
    }

    protected function getColorSpace(int $components): string
    {
        switch ($components) {
            case 1:
                return ColorSpace::GRAYSCALE;
            case 3:
                return ColorSpace::RGB;
            case 4:
                return ColorSpace::CMYK;
            default:
                throw new UnexpectedValueException('Invalid color space');
        }
    }

    protected function encodeColorProfile(string $data): string
    {
        $maxChunkSize = self::MAX_BYTES_IN_SEGMENT - strlen(self::ICC_PROFILE_HEADER) - 4;
        $chunks = str_split($data, $maxChunkSize);
        $count = count($chunks);

        for ($i = 0; $i < $count; $i++) {
            $value = self::ICC_PROFILE_HEADER . pack('CC', $i + 1, $count) . $chunks[$i];
            $chunks[$i] = "\xff\xe2" . pack('n', strlen($value) + 2) . $value;
        }

        return implode('', $chunks);
    }

    protected function getDecoder(): JPEGDecoder
    {
        return new JPEGDecoder();
    }
}
