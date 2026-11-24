<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('drcNumbers')]
    public function test_normalizes_drc_national_trunk_zero(string $raw, ?string $country): void
    {
        $this->assertSame('+243812345678', PhoneNumber::normalize($raw, $country));
    }

    public static function drcNumbers(): array
    {
        return [
            ['+243 081 234 5678', null],
            ['2430812345678', null],
            ['002430812345678', null],
            ['0812345678', 'CD'],
            ['+243812345678', null],
        ];
    }

    public function test_preserves_other_country_numbers_and_incomplete_drc_numbers(): void
    {
        $this->assertSame('+242050330038', PhoneNumber::normalize('+242050330038'));
        $this->assertSame('+237677123456', PhoneNumber::normalize('677 12 34 56', 'CM'));
        $this->assertSame('+243081234567', PhoneNumber::normalize('+243081234567'));
    }
}
