<?php

namespace Tests\Unit;

use App\Support\Sales\SalesFormPhone;
use PHPUnit\Framework\TestCase;

class SalesFormPhoneTest extends TestCase
{
    public function test_international_prefix_is_preserved_and_canonicalized(): void
    {
        $this->assertSame('+237699000001', SalesFormPhone::normalize('+237 6 99 00 00 01'));
        $this->assertSame('+237699000001', SalesFormPhone::normalize('00237 699 000 001'));
        $this->assertSame('+376312345', SalesFormPhone::normalize('+376 312 345'));
    }

    public function test_wrong_prefix_combined_with_cameroonian_national_digits_is_rejected(): void
    {
        $this->assertNull(SalesFormPhone::normalize('+376699000001'));
        $this->assertNull(SalesFormPhone::normalize('+237'));
        $this->assertNull(SalesFormPhone::normalize('+237699000001 ext 1'));
    }
}
