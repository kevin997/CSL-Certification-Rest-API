<?php

namespace Tests\Feature\Mail;

use App\Mail\TestMail;
use App\Models\EmailSuppression;
use App\Support\Mail\BounceMailbox;
use App\Support\Mail\DeliveryStatusReport;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Tests\TestCase;

/**
 * A mailbox that does not exist must be mailed once, not every week.
 *
 * The case this exists for: the Learner Weekly Digest bounced with 550 5.1.1
 * for a learner who signed up with a made-up Gmail address, and nothing read
 * the bounce, so it would have gone out again every Wednesday.
 */
class EmailSuppressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // config/mail.php pins the default mailer, so phpunit.xml's
        // MAIL_MAILER=array does not reach it; without this the test would
        // send through the real relay.
        config(['mail.default' => 'array']);
    }

    public function test_mail_to_a_suppressed_address_is_not_sent(): void
    {
        EmailSuppression::factory()->create(['email' => 'nobody-here@example.test']);

        Mail::to('Nobody-Here@Example.test')->send(new TestMail);

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_only_the_suppressed_recipient_is_dropped_from_a_shared_message(): void
    {
        EmailSuppression::factory()->create(['email' => 'nobody-here@example.test']);

        Mail::to(['learner@example.test', 'nobody-here@example.test'])->cc('nobody-here@example.test')->send(new TestMail);

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $this->assertSame(['learner@example.test'], array_map(fn ($address) => $address->getAddress(), $email->getTo()));
        $this->assertSame([], $email->getCc());
        $this->assertStringNotContainsString('Cc:', $email->toString());
    }

    public function test_mail_to_other_addresses_is_untouched(): void
    {
        Mail::to('learner@example.test')->send(new TestMail);

        $this->assertCount(1, $this->sentMessages());
    }

    public function test_a_hard_bounce_report_yields_the_failed_recipient(): void
    {
        $bounces = DeliveryStatusReport::hardBounces($this->report('ghost@example.test', '5.1.1'));

        $this->assertCount(1, $bounces);
        $this->assertSame('ghost@example.test', $bounces[0]->email);
        $this->assertSame('5.1.1', $bounces[0]->statusCode);
        $this->assertStringContainsString('does not exist', (string) $bounces[0]->diagnostic);
    }

    public function test_policy_refusals_full_mailboxes_and_delays_are_not_hard_bounces(): void
    {
        // A 5.7.1 is the receiver refusing *us* (reputation, policy); blocking
        // the learner for it would punish them for our sending.
        $this->assertSame([], DeliveryStatusReport::hardBounces($this->report('learner@example.test', '5.7.1')));
        $this->assertSame([], DeliveryStatusReport::hardBounces($this->report('learner@example.test', '5.2.2')));
        $this->assertSame([], DeliveryStatusReport::hardBounces($this->report('learner@example.test', '4.4.1', 'delayed')));
    }

    public function test_an_ordinary_email_is_not_a_bounce_report(): void
    {
        $plain = "From: someone@example.test\r\nSubject: Hello\r\nContent-Type: text/plain\r\n\r\nStatus: 5.1.1\r\nFinal-Recipient: rfc822; ghost@example.test\r\nAction: failed\r\n";

        $this->assertFalse(DeliveryStatusReport::isDeliveryStatusReport($plain));
        $this->assertSame([], DeliveryStatusReport::hardBounces($plain));
    }

    public function test_collecting_bounces_blocks_the_address_and_files_only_reports(): void
    {
        config(['mail.bounce_mailbox.username' => 'no.reply@example.test', 'mail.bounce_mailbox.password' => 'secret']);
        $mailbox = $this->mailbox([
            $this->report('ghost@example.test', '5.1.1'),
            $this->report('ghost@example.test', '5.1.1'),
            $this->report('learner@example.test', '5.7.1'),
            "From: MAILER-DAEMON@example.test\r\nSubject: Out of office\r\nContent-Type: text/plain\r\n\r\nI am away.",
        ]);

        $this->artisan('mail:collect-bounces')->assertSuccessful();

        $this->assertTrue(EmailSuppression::isSuppressed('ghost@example.test'));
        $this->assertFalse(EmailSuppression::isSuppressed('learner@example.test'));
        $this->assertSame(1, EmailSuppression::query()->count());
        $this->assertSame([true, true, true, false], $mailbox->filed);
    }

    public function test_a_dry_run_blocks_and_files_nothing(): void
    {
        config(['mail.bounce_mailbox.username' => 'no.reply@example.test', 'mail.bounce_mailbox.password' => 'secret']);
        $mailbox = $this->mailbox([$this->report('ghost@example.test', '5.1.1')]);

        $this->artisan('mail:collect-bounces', ['--dry-run' => true])
            ->expectsOutput('Would block ghost@example.test (5.1.1)')
            ->assertSuccessful();

        $this->assertSame([false], $mailbox->filed);
        $this->assertSame(0, EmailSuppression::query()->count());
    }

    public function test_collecting_without_mailbox_credentials_reads_nothing(): void
    {
        config(['mail.bounce_mailbox.username' => null, 'mail.bounce_mailbox.password' => null]);
        $mailbox = $this->mailbox([$this->report('ghost@example.test', '5.1.1')]);

        $this->artisan('mail:collect-bounces')->assertSuccessful();

        $this->assertSame([], $mailbox->filed);
        $this->assertSame(0, EmailSuppression::query()->count());
    }

    /** @return list<SentMessage> */
    private function sentMessages(): array
    {
        return array_values(iterator_to_array(Mail::mailer('array')->getSymfonyTransport()->messages()));
    }

    /**
     * A bounce shaped like the MailChannels report that started this.
     */
    private function report(string $recipient, string $status, string $action = 'failed'): string
    {
        $boundary = 'A38E94023C0.1790755228/relay.example.test';

        return implode("\r\n", [
            'From: MAILER-DAEMON@relay.example.test (Mail Delivery System)',
            'Subject: Undelivered Mail Returned to Sender',
            'MIME-Version: 1.0',
            "Content-Type: multipart/report; report-type=delivery-status;\r\n\tboundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            'Content-Description: Notification',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'I\'m sorry to have to inform you that your message could not be delivered.',
            '',
            "--{$boundary}",
            'Content-Description: Delivery report',
            'Content-Type: message/delivery-status',
            '',
            'Reporting-MTA: dns; relay.example.test',
            'X-Postfix-Sender: rfc822; no.reply@example.test',
            '',
            "Final-Recipient: rfc822; {$recipient}",
            "Original-Recipient: rfc822;{$recipient}",
            "Action: {$action}",
            "Status: {$status}",
            'Remote-MTA: dns; mx.example.test',
            'Diagnostic-Code: smtp; 550-5.1.1 The email account that you tried to reach does',
            '    not exist. Please try double-checking the recipient\'s email address.',
            '',
            "--{$boundary}",
            'Content-Description: Undelivered Message',
            'Content-Type: message/rfc822',
            '',
            'Subject: Your Learning Progress',
            '',
            'Hello',
            "--{$boundary}--",
            '',
        ]);
    }

    /**
     * @param  list<string>  $messages
     */
    private function mailbox(array $messages): BounceMailbox
    {
        $mailbox = new class($messages) implements BounceMailbox
        {
            /** @var list<bool> */
            public array $filed = [];

            /** @param list<string> $messages */
            public function __construct(private readonly array $messages) {}

            public function process(Closure $handle): int
            {
                foreach ($this->messages as $raw) {
                    $this->filed[] = $handle($raw);
                }

                return count(array_filter($this->filed));
            }
        };
        $this->app->instance(BounceMailbox::class, $mailbox);

        return $mailbox;
    }
}
