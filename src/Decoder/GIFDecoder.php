<?php

namespace ImageInfo\Decoder;

use Generator;
use InvalidArgumentException;
use UnexpectedValueException;

class GIFDecoder implements DecoderInterface
{
    protected const GIF_HEADERS = ['GIF87a', 'GIF89a'];

    public function decode(string &$data): Generator
    {
        if (!in_array(substr($data, 0, 6), self::GIF_HEADERS, true)) {
            throw new InvalidArgumentException('Invalid GIF data');
        }

        $position = 6;

        $desc = null;
        $label = null;

        while ($position < strlen($data)) {
            $offset = $position;

            switch (true) {
                case $offset === 6:
                    $type = 'LSD';
                    $size = 7;
                    $value = substr($data, $offset, 7);
                    $desc = $this->parseLogicalScreenDescriptor($value);
                    $position += $size;
                    break;

                case $offset === 13 && $desc['gctflag'] === 1:
                    $type = 'GCT';
                    $size = $this->getColorTableSize($desc['gctsize']);
                    $value = substr($data, $offset, $size);
                    $position += $size;
                    break;

                default:
                    $separator = $data[$position];
                    $position++;

                    $desc = null;
                    $label = null;

                    switch ($separator) {
                        case ',':
                            $type = 'IMG';
                            $desc = $this->parseImageDescriptor(substr($data, $position, 9));
                            $position += 9;
                            if ($desc['lctflag'] === 1) {
                                $lctSize = $this->getColorTableSize($desc['lctsize']);
                                $position += $lctSize;
                            }
                            $desc['lzwsize'] = ord($data[$position]);
                            $position++;
                            $position = $this->seekBlockEnd($data, $position);
                            $size = $position - $offset + 1;
                            break;

                        case '!':
                            $type = 'EXT';
                            $label = ord($data[$position]);
                            $position++;
                            $position = $this->seekBlockEnd($data, $position);
                            $size = $position - $offset + 1;
                            break;

                        case ';':
                            $type = 'END';
                            $size = 0;
                            break;

                        default:
                            throw new UnexpectedValueException('Unexpected block introducer');
                    }

                    $position++;
            }

            yield [
                'offset'   => $offset,
                'size'     => $size,
                'type'     => $type,
                'label'    => $label,
                'desc'     => $desc,
                'value'    => substr($data, $offset, $size),
                'position' => &$position
            ];
        }
    }

    protected function seekBlockEnd(string &$data, int $position): int
    {
        while ($position < strlen($data)) {
            if ($data[$position] === "\x00") {
                return $position;
            }
            $size = ord($data[$position]);
            $position += $size + 1;
        }
        return $position;
        throw new UnexpectedValueException('Unexpected end of data');
    }

    protected function parseLogicalScreenDescriptor(string $data): array
    {
        return [
            'width'    => unpack('v', $data, 0)[1],
            'height'   => unpack('v', $data, 2)[1],
            'packed'   => ord($data[4]),
            'gctflag'  => (ord($data[4]) & 0x80) >> 7,
            'colorres' => (ord($data[4]) & 0x70) >> 4,
            'sflag'    => (ord($data[4]) & 0x08) >> 3,
            'gctsize'  => (ord($data[4]) & 0x07),
            'bgindex'  => ord($data[5]),
            'pxratio'  => ord($data[6])
        ];
    }

    protected function parseImageDescriptor(string $data): array
    {
        return [
            'left'      => unpack('v', $data, 0)[1],
            'top'       => unpack('v', $data, 2)[1],
            'width'     => unpack('v', $data, 4)[1],
            'height'    => unpack('v', $data, 6)[1],
            'packed'    => ord($data[8]),
            'lctflag'   => (ord($data[8]) & 0x80) >> 7,
            'iflag'     => (ord($data[8]) & 0x40) >> 6,
            'sflag'     => (ord($data[8]) & 0x20) >> 5,
            'lctsize'   => (ord($data[8]) & 0x07)
        ];
    }

    protected function getColorTableSize(int $ctsize): int
    {
        return 3 * 2 ** ($ctsize + 1);
    }
}
