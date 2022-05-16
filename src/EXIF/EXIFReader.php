<?php

namespace ImageInfo\EXIF;

use Closure;
use UnexpectedValueException;

class EXIFReader
{
    protected const EXIF_LITTLE_ENDIAN = 'II';

    protected const EXIF_BIG_ENDIAN = 'MM';

    protected const EXIF_ENCODING_ASCII = "ASCII\x00\x00\x00";

    protected const EXIF_ENCODING_JIS = "JIS\x00\x00\x00\x00\x00";

    protected const EXIF_ENCODING_UNICODE = "UNICODE\x00";

    protected const EXIF_ENCODING_UNDEFINED = "\x00\x00\x00\x00\x00\x00\x00\x00";

    protected const IGNORED_SECTIONS = [
        'FileName',
        'FileDateTime',
        'FileSize',
        'FileType',
        'MimeType',
        'SectionsFound',
        'COMPUTED',
        'THUMBNAIL',
        'Exif_IFD_Pointer',
        'GPS_IFD_Pointer',
        'InteroperabilityOffset'
    ];

    protected const UNDEFINED_TAGS_TO_EXIF_TAGS = [
        'UndefinedTag:0x001F' => 'GPSHPositioningError',
        'UndefinedTag:0x9010' => 'OffsetTime',
        'UndefinedTag:0x9011' => 'OffsetTimeOriginal',
        'UndefinedTag:0x9012' => 'OffsetTimeDigitized',
        'UndefinedTag:0x8830' => 'SensitivityType',
        'UndefinedTag:0x8831' => 'StandardOutputSensitivity',
        'UndefinedTag:0x8832' => 'RecommendedExposureIndex',
        'UndefinedTag:0x8833' => 'ISOSpeed',
        'UndefinedTag:0x8834' => 'ISOSpeedLatitudeyyy',
        'UndefinedTag:0x8835' => 'ISOSpeedLatitudezzz',
        'UndefinedTag:0x9400' => 'Temperature',
        'UndefinedTag:0x9401' => 'Humidity',
        'UndefinedTag:0x9402' => 'Pressure',
        'UndefinedTag:0x9403' => 'WaterDepth',
        'UndefinedTag:0x9404' => 'Acceleration',
        'UndefinedTag:0x9405' => 'CameraElevationAngle',
        'UndefinedTag:0xA430' => 'CameraOwnerName',
        'UndefinedTag:0xA431' => 'BodySerialNumber',
        'UndefinedTag:0xA432' => 'LensSpecification',
        'UndefinedTag:0xA433' => 'LensMake',
        'UndefinedTag:0x0095' => 'LensModel', // Canon LensModel
        'UndefinedTag:0xA434' => 'LensModel',
        'UndefinedTag:0xA435' => 'LensSerialNumber'
    ];

    protected const TAG_ALIASES = [
        'SpectralSensity'   => 'SpectralSensitivity',
        'ISOSpeedRatings'   => 'PhotographicSensitivity',
        'SubjectLocation'   => 'SubjectArea',
        'GPSVersion'        => 'GPSVersionID',
        'GPSProcessingMode' => 'GPSProcessingMethod'
    ];

    protected static array $EXIFTable;

    public function __construct()
    {
        static::$EXIFTable ??= require __DIR__ . '/tables/EXIF.php';
    }

    public function read(string &$data): array
    {
        $rawTags = $this->readTagsFromString($data);

        $byteOrder = $rawTags['COMPUTED']['ByteOrderMotorola']
            ? self::EXIF_BIG_ENDIAN
            : self::EXIF_LITTLE_ENDIAN;

        foreach (self::IGNORED_SECTIONS as $key) {
            unset($rawTags[$key]);
        }

        foreach (self::UNDEFINED_TAGS_TO_EXIF_TAGS as $source => $dest) {
            if (isset($rawTags[$source])) {
                $rawTags[$dest] = $rawTags[$source];
                unset($rawTags[$source]);
            }
        }

        $tags = [];

        foreach ($rawTags as $key => $value) {
            if (str_starts_with($key, 'UndefinedTag:')) {
                continue;
            }

            $parsedValue = $value;

            switch (static::$EXIFTable[$key]['type'] ?? null) {
                case 'binary':
                    $parsedValue = $this->parseBinary($value);
                    break;

                case 'coords':
                    $refKey = static::$EXIFTable[$key]['ref'];
                    $parsedValue = $this->parseCoordinates($value, $rawTags[$refKey] ?? null);
                    break;

                case 'datetime':
                    $subsecondsKey = static::$EXIFTable[$key]['subseconds'];
                    $timeoffsetKey = static::$EXIFTable[$key]['timeoffset'];
                    $parsedValue = $this->parseDateTime(
                        $value,
                        $rawTags[$subsecondsKey] ?? null,
                        $rawTags[$timeoffsetKey] ?? null
                    );
                    break;

                case 'rational':
                    $parsedValue = is_array($value)
                        ? array_map(fn (string $value): float => $this->parseRational($value), $value)
                        : $this->parseRational($value);
                    break;

                case 'text':
                    $parsedValue = $this->parseText($value, $byteOrder);
                    break;

                case 'version':
                    $parsedValue = $this->parseVersion($value);
                    break;
            }

            if (isset(static::$EXIFTable[$key]['description'])) {
                $description = static::$EXIFTable[$key]['description'];
                if (is_array($description)) {
                    $parsedValue = $description[$parsedValue] ?? $parsedValue;
                }
                if ($description instanceof Closure) {
                    $parsedValue = $description($parsedValue);
                }
            }

            if (is_string($parsedValue) && mb_check_encoding($parsedValue, 'UTF-8') === false) {
                continue;
            }

            $tags[$key] = $value !== $parsedValue ? [$value, $parsedValue] : [$value];

            if (isset(self::TAG_ALIASES[$key])) {
                $alias = self::TAG_ALIASES[$key];
                $tags[$alias] = &$tags[$key];
            }
        }

        return $tags;
    }

    protected function readTagsFromString(string $data): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $data);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);
        if ($exif === false) {
            throw new UnexpectedValueException(error_get_last()['message']);
        }
        return $exif;
    }

    protected function parseBinary(string $value): string
    {
        for ($i = 0; $i < strlen($value); $i++) {
            $code = ord($value[$i]);
            if ($code > 9) {
                throw new UnexpectedValueException(sprintf('Character \\x%x cannot be represented by a single decimal digit', $code));
            }
            $value[$i] = $code;
        }
        return $value;
    }

    protected function parseCoordinates(array $value, ?string $cardinalRef): float
    {
        [$degrees, $minutes, $seconds] = array_map(
            fn (string $value): float => $this->parseRational($value),
            array_replace([0, 0, 0], $value)
        );
        $direction = ($cardinalRef === 'S' || $cardinalRef === 'W') ? -1 : 1;
        return $direction * ($degrees + $minutes / 60 + $seconds / 3600);
    }

    protected function parseDateTime(string $value, ?string $subseconds, ?string $timeoffset): ?EXIFDateTime
    {
        $dateTime = EXIFDateTime::createFromEXIFData($value, $subseconds, $timeoffset);
        return $dateTime === false ? null : $dateTime;
    }

    protected function parseRational(string $value): float
    {
        [$num, $den] = explode('/', $value . '/1');
        return $num / $den;
    }

    protected function parseText(string &$value, string $byteOrder): ?string
    {
        $encoding = $this->getTextEncoding($value, $byteOrder);
        $text = mb_convert_encoding(substr($value, 8), 'UTF-8', $encoding);
        return $text === false ? null : rtrim($text, "\x00");
    }

    protected function getTextEncoding(string &$value, string $byteOrder): string
    {
        switch (substr($value, 0, 8)) {
            case self::EXIF_ENCODING_ASCII:
                return 'ASCII';
            case self::EXIF_ENCODING_JIS:
                return 'JIS';
            case self::EXIF_ENCODING_UNICODE:
                return $byteOrder === self::EXIF_BIG_ENDIAN ? 'UCS-2BE' : 'UCS-2LE';
            case self::EXIF_ENCODING_UNDEFINED:
            default:
                return 'auto';
        }
    }

    protected function parseVersion(string $value): string
    {
        return sprintf('%d.%d', substr($value, 0, 2), substr($value, 2, 2));
    }
}
