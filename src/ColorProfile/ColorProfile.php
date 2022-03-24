<?php

namespace ImageInfo\ColorProfile;

use InvalidArgumentException;
use UnexpectedValueException;

class ColorProfile
{
    protected const ICC_PROFILE_SIGNATURE = 'acsp';

    protected const ICC_PROFILE_SIGNATURE_OFFSET = 36;

    protected string $data;

    protected array $tags;

    public function __construct(string $data)
    {
        $this->data = $data;

        if (strpos($this->data, self::ICC_PROFILE_SIGNATURE) !== self::ICC_PROFILE_SIGNATURE_OFFSET) {
            throw new InvalidArgumentException('Invalid ICC profile data');
        }

        $this->tags = $this->getTags();
    }

    public function name(): string
    {
        return $this->getTagValue('desc', '');
    }

    public function copyright(): string
    {
        return $this->getTagValue('cprt', '');
    }

    public function profileVersion(): string
    {
        return sprintf('%u.%u.%u', ord($this->data[8]), (ord($this->data[9]) & 0xf0) >> 4, ord($this->data[9]) & 0x0f);
    }

    public function deviceClass(): string
    {
        return substr($this->data, 12, 4);
    }

    public function colorSpace(): string
    {
        return trim(substr($this->data, 16, 4));
    }

    public function connectionSpace(): string
    {
        return trim(substr($this->data, 20, 4));
    }

    public function primaryPlatform(): string
    {
        return substr($this->data, 40, 4);
    }

    public function renderingIntent(): string
    {
        $renderingIntent = unpack('N', $this->data, 64)[1];
        switch ($renderingIntent) {
            case 0:
                return RenderingIntent::PERCEPTUAL;
            case 1:
                return RenderingIntent::MEDIA_RELATIVE;
            case 3:
                return RenderingIntent::SATURATION;
            case 4:
                return RenderingIntent::ICC_ABSOLUTE;
            default:
                throw new UnexpectedValueException('Unexpected rendering intent');
        }
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function export(string $path): void
    {
        file_put_contents($path, $this->data);
    }

    public static function fromFile(string $path): ColorProfile
    {
        return new static(file_get_contents($path));
    }

    protected function getTags(): array
    {
        $tags = [];
        $position = 128;
        $count = unpack('N', $this->data, $position)[1];
        $position += 4;
        for ($i = 0; $i < $count; $i++) {
            $info = unpack('Z4tag/Noffset/Nlength', $this->data, $position);
            $tag = array_shift($info);
            $tags[$tag] = $info;
            $position += 12;
        }
        return $tags;
    }

    protected function getTagValue(string $name, string $default = null)
    {
        if (!isset($this->tags[$name])) {
            return $default;
        }
        ['offset' => $offset, 'length' => $length] = $this->tags[$name];
        $value = substr($this->data, $offset, $length);
        $type = substr($value, 0, 4);
        switch ($type) {
            case 'text':
                return substr($value, 8);
            case 'desc':
                return unpack('Z*', $value, 12)[1];
            case 'mluc':
                $strings = $this->parseMlucString($value);
                return array_shift($strings);
            default:
                return $default;
        }
    }

    protected function parseMlucString(string $data): array
    {
        $result = [];
        $position = 0;
        $type = substr($data, 0, 4);
        if ($type !== 'mluc') {
            throw new InvalidArgumentException('Invalid mluc tag');
        }
        $position += 8;
        $records = unpack('N', $data, $position)[1];
        $position += 8;
        for ($i = 0; $i < $records; $i++) {
            $langCode = substr($data, $position, 4);
            $position += 4;
            $stringLength = unpack('N', $data, $position)[1];
            $position += 4;
            $stringOffset = unpack('N', $data, $position)[1];
            $result[$langCode] = substr($data, $stringOffset, $stringLength);
        }
        return $result;
    }
}
