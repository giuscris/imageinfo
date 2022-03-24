<?php

namespace ImageInfo\Decoder;

use Generator;
use InvalidArgumentException;

class PNGDecoder implements DecoderInterface
{
    protected const PNG_HEADER = "\x89PNG\x0d\x0a\x1a\x0a";

    public function decode(string &$data): Generator
    {
        if (strpos($data, self::PNG_HEADER) !== 0) {
            throw new InvalidArgumentException('Invalid PNG data');
        }

        $position = strlen(self::PNG_HEADER);

        while ($position < strlen($data)) {
            $offset = $position;
            $size = unpack('N', $data, $position)[1];
            $position += 4;
            $type = substr($data, $position, 4);
            $position += $size + 4;
            $checksum = unpack('N', $data, $position)[1];
            $position += 4;

            yield [
                'offset'   => $offset,
                'size'     => $size,
                'type'     => $type,
                'value'    => substr($data, $offset + 8, $size),
                'checksum' => $checksum,
                'position' => &$position
            ];
        }
    }
}
