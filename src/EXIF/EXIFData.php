<?php

namespace ImageInfo\EXIF;

class EXIFData
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function parsedData()
    {
        foreach ($this->data as $key => $value) {
            yield $key => $value[1] ?? $value[0];
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function hasMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->has($key)) {
                return false;
            }
        }
        return true;
    }

    public function getRaw(string $key, $default = null)
    {
        return $this->has($key) ? $this->data[$key][0] : $default;
    }

    public function get(string $key, $default = null)
    {
        return $this->has($key)
            ? $this->data[$key][1] ?? $this->data[$key][0]
            : $default;
    }
}
