<?php

namespace ImageInfo\EXIF;

class EXIFData
{
    protected EXIFReader $reader;

    protected string $data;

    protected array $tags;

    public function __construct(string $data)
    {
        $this->reader = new EXIFReader();
        $this->data = $data;
        $this->tags = $this->reader->read($this->data);
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function parsedTags()
    {
        foreach ($this->tags as $key => $value) {
            yield $key => $value[1] ?? $value[0];
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->tags);
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
        return $this->has($key) ? $this->tags[$key][0] : $default;
    }

    public function get(string $key, $default = null)
    {
        return $this->has($key)
            ? $this->tags[$key][1] ?? $this->tags[$key][0]
            : $default;
    }
}
