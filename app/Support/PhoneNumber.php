<?php

namespace App\Support;

/**
 * Cameroon-first phone normalizer → E.164 ("+237XXXXXXXXX").
 *
 * Users enter numbers every possible way; before we hand a number to WhatsApp
 * (Wachap) it must be a single canonical shape. This handles the common local
 * variants:
 *   - missing country code:            677 12 34 56   → +237677123456
 *   - missing the leading mobile 6:    77 12 34 56    → +237677123456
 *   - national trunk zero:             0677123456     → +237677123456
 *   - 00 international prefix:          00237677123456 → +237677123456
 *   - already E.164 / other country:   +2348012345678 → +2348012345678 (kept)
 *
 * Output is always E.164 with a leading "+", matching how numbers are stored
 * (customer normalized_phone_number, intl-tel-input getNumber()) and what the
 * Wachap `to` field expects. A number that can't be understood is returned as
 * best-effort "+<digits>" (never throws), and empty input yields "".
 */
class PhoneNumber
{
    /** Calling codes for the countries this platform serves. */
    private const CALLING_CODES = [
        'CM' => '237', 'GA' => '241', 'TD' => '235', 'CF' => '236', 'CG' => '242',
        'GQ' => '240', 'CI' => '225', 'SN' => '221', 'BJ' => '229', 'BF' => '226',
        'ML' => '223', 'TG' => '228', 'NE' => '227', 'GN' => '224', 'NG' => '234',
        'GH' => '233', 'FR' => '33', 'US' => '1', 'GB' => '44',
    ];

    public static function normalize(string $raw, ?string $defaultCountry = null): string
    {
        $raw = trim($raw);
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        $country = strtoupper($defaultCountry ?? (string) config('app.default_country', 'CM'));
        $code = self::CALLING_CODES[$country] ?? '237';

        if (str_starts_with($digits, '00')) {
            // 00 = international access prefix → drop it, the rest is E.164 digits.
            $digits = substr($digits, 2);
        } elseif (! $hasPlus && ! str_starts_with($digits, $code)) {
            // A national number for the default country: strip the trunk 0 and
            // prepend the calling code — but only when it's short enough to BE a
            // national number (don't re-prefix a number that already carries a
            // different country code).
            $national = ltrim($digits, '0');
            if (strlen($national) <= 9) {
                $digits = $code.$national;
            } else {
                $digits = $national;
            }
        }

        // Cameroon mobile subscriber numbers are 9 digits starting with 6; a
        // supplied 8-digit subscriber part is missing that leading 6.
        if ($code === '237' && str_starts_with($digits, '237')) {
            $subscriber = substr($digits, 3);
            if (strlen($subscriber) === 8) {
                $digits = '237'.'6'.$subscriber;
            }
        }

        return '+'.$digits;
    }
}
