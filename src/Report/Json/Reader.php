<?php

declare(strict_types=1);

namespace WcagScout\Report\Json;

use InvalidArgumentException;

/**
 * Typed, path-aware access to decoded JSON, so that a malformed report fails with a message
 * like "pages[3].findings[0].severity must be a string" instead of a type error.
 *
 * @internal
 */
final readonly class Reader
{
    private function __construct(
        private mixed $value,
        private string $path,
    ) {}

    public static function of(mixed $value, string $path): self
    {
        return new self($value, $path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function string(string $key): string
    {
        return $this->get($key)->asString();
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        return $value->value === null ? null : $value->asString();
    }

    public function int(string $key): int
    {
        $value = $this->get($key)->value;

        return \is_int($value) ? $value : throw $this->error($key, 'an integer');
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->get($key)->value;
        if ($value === null || \is_int($value)) {
            return $value;
        }

        throw $this->error($key, 'an integer or null');
    }

    /** @return list<string> */
    public function strings(string $key): array
    {
        return array_map(static fn(self $r): string => $r->asString(), $this->list($key));
    }

    /**
     * @param bool $allowMap also accept a JSON object and return its values
     * @return list<self>
     */
    public function list(string $key, bool $allowMap = false): array
    {
        $value = $this->get($key)->value;
        if (!\is_array($value) || (!$allowMap && !array_is_list($value))) {
            throw $this->error($key, 'a list');
        }

        $items = [];
        $index = 0;
        foreach ($value as $itemKey => $item) {
            $items[] = new self($item, \sprintf('%s.%s[%s]', $this->path, $key, $allowMap ? $itemKey : $index));
            ++$index;
        }

        return $items;
    }

    /** @return array<string, self> */
    public function map(string $key): array
    {
        $value = $this->get($key)->value;
        if (!\is_array($value)) {
            throw $this->error($key, 'an object');
        }

        $items = [];
        foreach ($value as $itemKey => $item) {
            $items[(string) $itemKey] = new self($item, $this->path . '.' . $key . '.' . $itemKey);
        }

        return $items;
    }

    public function asString(): string
    {
        return \is_string($this->value) ? $this->value : throw new InvalidArgumentException($this->path . ' must be a string.');
    }

    /** @return scalar|list<string>|null */
    public function asScalarOrList(): mixed
    {
        if ($this->value === null || \is_scalar($this->value)) {
            return $this->value;
        }
        if (\is_array($this->value) && array_is_list($this->value)) {
            return array_map(static fn(mixed $v): string => \is_scalar($v) ? (string) $v : '', $this->value);
        }

        throw new InvalidArgumentException($this->path . ' must be a scalar or a list.');
    }

    private function get(string $key): self
    {
        if (!\is_array($this->value) || !\array_key_exists($key, $this->value)) {
            throw new InvalidArgumentException(\sprintf('%s.%s is missing.', $this->path, $key));
        }

        return new self($this->value[$key], $this->path . '.' . $key);
    }

    private function error(string $key, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(\sprintf('%s.%s must be %s.', $this->path, $key, $expected));
    }
}
