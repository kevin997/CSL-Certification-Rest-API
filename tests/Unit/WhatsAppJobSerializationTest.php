<?php

namespace Tests\Unit;

use App\Jobs\SendWhatsAppNotification;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WhatsAppJobSerializationTest extends TestCase
{
    public function test_job_payload_from_before_the_reminder_fields_can_still_run(): void
    {
        $job = (new ReflectionClass(SendWhatsAppNotification::class))->newInstanceWithoutConstructor();
        $job->__unserialize(['phoneNumber' => '237699000001', 'message' => 'Existing message']);

        $this->assertNull($job->pendingOrderId);
        $this->assertFalse($job->paymentReminder);
        $this->assertSame([10], $job->backoff());
    }
}
