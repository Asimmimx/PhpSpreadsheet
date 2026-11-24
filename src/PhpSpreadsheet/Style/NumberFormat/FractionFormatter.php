<?php

namespace PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FractionFormatter extends BaseFormatter
{
    /** @param null|bool|float|int|string $value  value to be formatted */
    public static function format(mixed $value, string $format): string
    {
        // A 0 placeholder for the last digit of the numerator shows a numerator of 0
        $showZeroNumerator = str_contains($format, '0/');
        // The number of digit placeholders in the denominator limits its size,
        // e.g. "?/?" allows denominators up to 9 and "??/??" up to 99
        $maxDivisor = 9;
        if (preg_match('/\/([#0?]+)/', self::stripQuotes($format), $divisorMatches) === 1) {
            $maxDivisor = (10 ** strlen($divisorMatches[1])) - 1;
        }
        // It's a dirty hack; but replace # and 0 digit placeholders with ?
        $format = (string) preg_replace('/[#0]+\//', '?/', $format);
        $format = (string) preg_replace('/\/[#0]+/', '/?', $format);
        $format = self::stripQuotes($format);
        $value = (float) $value;
        $absValue = abs($value);

        $sign = ($value < 0.0) ? '-' : '';

        $integerPart = floor($absValue);

        preg_match('/(#?.*\?)\/(\?+|\d+)/', $format, $matches);
        $formatIntegerPart = $matches[1] ?? '0';
        $fixedDivisor = (isset($matches[2]) && is_numeric($matches[2])) ? (int) $matches[2] : null;
        $isVulgar = !str_contains($formatIntegerPart, '0')
            && !str_contains($formatIntegerPart, '#')
            && !str_starts_with($formatIntegerPart, '? ?');

        if ($fixedDivisor !== null) {
            $adjustedDecimalPart = (int) round(($absValue - $integerPart) * $fixedDivisor);
            $adjustedDecimalDivisor = $fixedDivisor;
        } else {
            [$adjustedDecimalPart, $adjustedDecimalDivisor] = self::approximate($absValue - $integerPart, $maxDivisor);
        }
        if ($adjustedDecimalDivisor > 0 && $adjustedDecimalPart >= $adjustedDecimalDivisor) {
            // The fraction rounded up to a whole number
            ++$integerPart;
            $adjustedDecimalPart = 0;
        }

        if ($adjustedDecimalPart === 0) {
            // An integer is written as a fraction when the format has no integer part,
            // or when the numerator shows 0; otherwise the fraction is left out
            if (!$isVulgar && !$showZeroNumerator) {
                return "{$sign}{$integerPart}";
            }
            $adjustedDecimalDivisor = $fixedDivisor ?? 1;
        }

        if ((str_contains($formatIntegerPart, '0'))) {
            return "{$sign}{$integerPart} {$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
        } elseif ((str_contains($formatIntegerPart, '#'))) {
            if ($integerPart == 0) {
                return "{$sign}{$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
            }

            return "{$sign}{$integerPart} {$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
        } elseif ((str_starts_with($formatIntegerPart, '? ?'))) {
            if ($integerPart == 0) {
                $integerPart = '';
            }

            return "{$sign}{$integerPart} {$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
        }

        $adjustedDecimalPart += $integerPart * $adjustedDecimalDivisor;

        return "{$sign}{$adjustedDecimalPart}/{$adjustedDecimalDivisor}";
    }

    /**
     * Find the fraction closest to $value (0 <= $value < 1) whose denominator
     * is not greater than $maxDivisor, using continued fractions.
     *
     * @return array{int, int} numerator and denominator
     */
    private static function approximate(float $value, int $maxDivisor): array
    {
        [$p0, $q0, $p1, $q1] = [0, 1, 1, 0];
        $remainder = $value;
        while (true) {
            $a = (int) floor($remainder);
            $q2 = $q0 + $a * $q1;
            if ($q2 > $maxDivisor) {
                break;
            }
            [$p0, $q0, $p1, $q1] = [$p1, $q1, $p0 + $a * $p1, $q2];
            $fraction = $remainder - $a;
            if ($fraction < 1e-9 || abs($value - $p1 / $q1) < 1e-12) {
                return [$p1, $q1];
            }
            $remainder = 1 / $fraction;
        }

        // Compare the last convergent with the best semiconvergent that still fits
        $k = intdiv($maxDivisor - $q0, $q1);
        $semiNumerator = $p0 + $k * $p1;
        $semiDivisor = $q0 + $k * $q1;
        if (abs($value - $semiNumerator / $semiDivisor) < abs($value - $p1 / $q1)) {
            return [$semiNumerator, $semiDivisor];
        }

        return [$p1, $q1];
    }
}
