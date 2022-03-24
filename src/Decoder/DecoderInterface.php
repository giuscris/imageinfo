<?php

namespace ImageInfo\Decoder;

use Generator;

interface DecoderInterface
{
    /**
     * Decode image data
     */
    public function decode(string &$data): Generator;
}
