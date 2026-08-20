<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

/**
 * Narrowing helpers for the sidecar payload. The sidecar is an external boundary,
 * so nothing arriving from it is trusted to be the right type.
 *
 * @internal
 */
final readonly class Cast
{
    public static function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            $string = self::string($item);

            if ($string !== null) {
                $strings[] = $string;
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rowList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $rows[] = $item;
            }
        }

        return $rows;
    }
}
