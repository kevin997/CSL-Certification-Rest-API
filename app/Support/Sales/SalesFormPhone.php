<?php

namespace App\Support\Sales;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class SalesFormPhone
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(?:\+|00)[0-9 ().-]{3,40}$/D', trim($value))) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        $phones = PhoneNumberUtil::getInstance();
        try {
            $phone = $phones->parse($value, null);
        } catch (NumberParseException) {
            return null;
        }

        return $phones->isValidNumber($phone) ? $phones->format($phone, PhoneNumberFormat::E164) : null;
    }
}
