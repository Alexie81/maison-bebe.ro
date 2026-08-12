<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use InvalidArgumentException;

final class Decimal
{
    public static function normalize(mixed $value, int $scale): string
    {
        $value = str_replace([' ', ','], ['', '.'], trim((string) $value));
        if ($value === '') {
            $value = '0';
        }
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Valoare numerică invalidă.');
        }
        return self::round($value, $scale);
    }

    public static function add(string $left, string $right, int $scale): string
    {
        return bcadd($left, $right, $scale);
    }

    public static function sub(string $left, string $right, int $scale): string
    {
        return bcsub($left, $right, $scale);
    }

    public static function mul(string $left, string $right, int $scale): string
    {
        return bcmul($left, $right, $scale);
    }

    public static function div(string $left, string $right, int $scale): string
    {
        if (bccomp($right, '0', max(4, $scale)) === 0) {
            return self::zero($scale);
        }
        return bcdiv($left, $right, $scale);
    }

    public static function cmp(string $left, string $right = '0', int $scale = 6): int
    {
        return bccomp($left, $right, $scale);
    }

    public static function abs(string $value, int $scale): string
    {
        return self::cmp($value, '0', $scale) < 0 ? bcsub('0', $value, $scale) : bcadd($value, '0', $scale);
    }

    public static function round(string $value, int $scale): string
    {
        $workScale = $scale + 1;
        $value = bcadd($value, '0', $workScale);
        $half = $scale === 0 ? '0.5' : '0.' . str_repeat('0', $scale) . '5';
        $adjusted = self::cmp($value, '0', $workScale) < 0
            ? bcsub($value, $half, $workScale)
            : bcadd($value, $half, $workScale);
        return bcadd($adjusted, '0', $scale);
    }

    public static function zero(int $scale): string
    {
        return $scale > 0 ? '0.' . str_repeat('0', $scale) : '0';
    }
}
