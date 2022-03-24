<?php

namespace ImageInfo\Decoder;

use Generator;
use InvalidArgumentException;

class JPEGDecoder implements DecoderInterface
{
    public function decode(string &$data): Generator
    {
        $position = 0;

        while ($position < strlen($data)) {
            $position = strpos($data, "\xff", $position);

            if ($position === false) {
                throw new InvalidArgumentException('Invalid JPEG data');
            }

            $offset = $position;
            $position += 1;
            $type = ord($data[$position]);
            $position += 1;

            if (($type > 0x00 && $type < 0xd0) || ($type > 0xda && $type < 0xff)) {
                $size = unpack('n', $data, $position)[1];
            } elseif ($type === 0xda) {
                $size = $this->seekSegmentEnd($data, $position) - $position;
            } else {
                $size = 0;
            }

            $position += $size;

            yield [
                'offset'   => $offset,
                'size'     => $size,
                'type'     => $type,
                'value'    => $size > 0 ? substr($data, $offset + 4, $size - 2) : null,
                'position' => &$position
            ];
        }
    }

    protected function seekSegmentEnd(string &$data, int $position): int
    {
        while ($position < strlen($data)) {
            $position = strpos($data, "\xff", $position);
            $position += 1;
            $type = ord($data[$position]);
            if (($type > 0x00 && $type < 0xd0) || $type > 0xd7) {
                return $position - 1;
            }
            $position += 2;
        }
    }
}
