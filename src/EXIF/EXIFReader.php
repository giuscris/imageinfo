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
        'SpectralSensity' => 'SpectralSensitivity',
        'ISOSpeedRatings' => 'PhotographicSensitivity',
        'SubjectLocation' => 'SubjectArea'
    ];

    protected string $byteOrder;

    protected array $data;

    protected array $EXIFTable;

    public function __construct(array $data)
    {
        $this->EXIFTable = require __DIR__ . '/tables/EXIF.php';
        $this->byteOrder = $this->getByteOrder($data);
        $this->data = $this->parse($data);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public static function fromFile(string $path): EXIFReader
    {
        $stream = fopen($path, 'r');
        $instance = static::fromStream($stream);
        fclose($stream);
        return $instance;
    }

    public static function fromString(string $data): EXIFReader
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $data);
        rewind($stream);
        $instance = static::fromStream($stream);
        fclose($stream);
        return $instance;
    }

    public static function fromStream($stream): EXIFReader
    {
        $exif = @exif_read_data($stream);
        if ($exif === false) {
            throw new UnexpectedValueException(error_get_last()['message']);
        }
        return new static($exif);
    }

    protected function parse(array &$data): array
    {
        foreach (self::IGNORED_SECTIONS as $key) {
            unset($data[$key]);
        }

        foreach (self::UNDEFINED_TAGS_TO_EXIF_TAGS as $source => $dest) {
            if (isset($data[$source])) {
                $data[$dest] = $data[$source];
                unset($data[$source]);
            }
        }

        $parsedData = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'UndefinedTag:')) {
                continue;
            }

            if ($key === 'UserComment') {
                try {
                    $value = rtrim($this->parseUserComment($value), "\x00");
                } catch (UnexpectedValueException $e) {
                    continue;
                }
            }

            if (is_string($value) && mb_check_encoding($value, 'UTF-8') === false) {
                continue;
            }

            $parsedValue = $value;

            if (isset($this->EXIFTable[$key]['description'])) {
                $description = $this->EXIFTable[$key]['description'];
                if (is_array($description)) {
                    $parsedValue = $description[$value] ?? $value;
                }
                if ($description instanceof Closure) {
                    $parsedValue = $description($value);
                }
            }

            if (isset($this->EXIFTable[$key]['type'])) {
                switch ($this->EXIFTable[$key]['type']) {
                    case 'rational':
                        $parsedValue = is_array($data[$key])
                            ? array_map([$this, 'parseRational'], $data[$key])
                            : $this->parseRational($data[$key]);
                        break;
                    case 'datetime':
                        $subsecondsKey = $this->EXIFTable[$key]['subseconds'];
                        $timeoffsetKey = $this->EXIFTable[$key]['timeoffset'];
                        $EXIFDateTime = EXIFDateTime::createFromEXIFTags($data[$key], $data[$subsecondsKey] ?? null, $data[$timeoffsetKey] ?? null);
                        if ($EXIFDateTime === false) {
                            break;
                        }
                        $parsedValue = $EXIFDateTime;
                        break;
                    case 'coords':
                        $dst = array_map([static::class, 'parseRational'], array_replace([0, 0, 0], $data[$key]));
                        $parsedValue = $this->parseCoordinates($dst, $data[$this->EXIFTable[$key]['ref']] ?? null);
                        break;
                    case 'version':
                        $parsedValue = $this->parseVersion($data[$key]);
                        break;
                }
            }

            $parsedData[$key] = $value !== $parsedValue ? [$value, $parsedValue] : [$value];

            if (isset(self::TAG_ALIASES[$key])) {
                $alias = self::TAG_ALIASES[$key];
                $parsedData[$alias] = &$parsedData[$key];
            }
        }

        return $parsedData;
    }

    protected function getByteOrder(array &$data): string
    {
        return $data['COMPUTED']['ByteOrderMotorola'] ? self::EXIF_BIG_ENDIAN : self::EXIF_LITTLE_ENDIAN;
    }

    protected function parseRational(?string $fraction): ?float
    {
        if ($fraction === null) {
            return null;
        }
        [$num, $den] = explode('/', $fraction . '/1');
        return $num / $den;
    }

    protected function parseCoordinates(array $dms, ?string $cardinalRef): float
    {
        [$degrees, $minutes, $seconds] = $dms;
        $direction = ($cardinalRef === 'S' || $cardinalRef === 'W') ? -1 : 1;
        return $direction * (float) round($degrees + $minutes / 60 + $seconds / 3600, 6);
    }

    protected function parseVersion(string $version): string
    {
        return sprintf('%d.%d', substr($version, 0, 2), substr($version, 2, 2));
    }

    protected function getUserCommentEncoding(string $data)
    {
        switch (substr($data, 0, 8)) {
            case self::EXIF_ENCODING_ASCII:
                return 'ASCII';
            case self::EXIF_ENCODING_JIS:
                return 'JIS';
            case self::EXIF_ENCODING_UNICODE:
                return $this->byteOrder === self::EXIF_BIG_ENDIAN ? 'UCS-2BE' : 'UCS-2LE';
            case self::EXIF_ENCODING_UNDEFINED:
                return 'auto';
            default:
                return null;
        }
    }

    protected function parseUserComment(string $data): ?string
    {
        $encoding = $this->getUserCommentEncoding($data);
        if ($encoding === null) {
            throw new UnexpectedValueException('Invalid user comment encoding');
        }
        $userComment = mb_convert_encoding(substr($data, 8), 'UTF-8', $encoding);
        if ($userComment === false) {
            throw new UnexpectedValueException('Cannot convert user comment to UTF-8');
        }
        return $userComment;
    }
}
