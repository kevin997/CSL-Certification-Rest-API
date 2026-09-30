<?php

namespace Tests\Feature\Mail;

use App\Listeners\MailBackupWithAttachment;
use Illuminate\Support\Facades\Event;
use Spatie\Backup\Events\BackupZipWasCreated;
use Tests\TestCase;

/**
 * Event discovery already registers every listener in app/Listeners. The
 * backup listener was also registered by hand, so each backup was emailed
 * twice.
 */
class BackupMailListenerTest extends TestCase
{
    public function test_the_backup_email_listener_is_registered_exactly_once(): void
    {
        $registrations = array_filter(
            Event::getRawListeners()[BackupZipWasCreated::class] ?? [],
            fn (mixed $listener): bool => is_string($listener) && str_starts_with($listener, MailBackupWithAttachment::class),
        );

        $this->assertCount(1, $registrations);
    }
}
