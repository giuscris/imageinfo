<?php

namespace ImageInfo\EXIF;

use DateTimeImmutable;

class EXIFDateTime extends DateTimeImmutable
{
    public const EXIF = 'Y:m:d H:i:s';

    public const EXIF_EXTENDED = self::EXIF . '.uP';

    /**
     * Create a new EXIFDateTime object from EXIF tags, e.g. DateTime, SubSecTime, TimeOffset
     *
     * @return  EXIFDateTime|bool
     */
    public static function createFromEXIFTags(string $datetime, string $subseconds = null, string $timeoffset = null)
    {
        return parent::createFromFormat(self::EXIF_EXTENDED, sprintf('%s.%s%s', $datetime, rtrim($subseconds ?? '0', "\x00\x20"), $timeoffset ?? '+00:00'));
    }

    public function __toString(): string
    {
        return $this->format(self::EXIF_EXTENDED);
    }
}
