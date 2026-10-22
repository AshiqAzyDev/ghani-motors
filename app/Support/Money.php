<?php

namespace App\Support;

class Money
{
    public static function gbp(?int $pence, bool $penceWhenNeeded = true): string
    {
        if ($pence === null) {
            return '—';
        }

        $sign = $pence < 0 ? '−' : '';
        $abs = abs($pence);

        if ($penceWhenNeeded && $abs % 100 !== 0) {
            return $sign.'£'.number_format($abs / 100, 2);
        }

        return $sign.'£'.number_format(intdiv($abs, 100));
    }

    public static function poundsToPence(mixed $pounds): int
    {
        if ($pounds === null || $pounds === '') {
            return 0;
        }

        return (int) round(((float) $pounds) * 100);
    }

    public static function nullablePounds(mixed $pounds): ?int
    {
        if ($pounds === null || $pounds === '') {
            return null;
        }

        return (int) round(((float) $pounds) * 100);
    }
}
