<?php

namespace App\Listeners;

use App\Models\EmailSuppression;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * The one gate every outgoing email passes through — mailables, notifications,
 * queued or not, whichever mailer — so a suppressed address is dropped without
 * each of Kursa's twenty-odd mails having to remember to check.
 *
 * Returning false cancels the message; Mailer::shouldSendMessage() treats it
 * as "do not send" and nothing is handed to the transport.
 */
class DropSuppressedRecipients
{
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;
        $recipients = [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()];
        $suppressed = EmailSuppression::suppressedAmong(array_map(fn (Address $address): string => $address->getAddress(), $recipients));

        if ($suppressed === []) {
            return null;
        }

        $keep = fn (array $addresses): array => array_values(array_filter(
            $addresses,
            fn (Address $address): bool => ! in_array(EmailSuppression::normalize($address->getAddress()), $suppressed, true),
        ));

        $to = $keep($message->getTo());
        $cc = $keep($message->getCc());
        $bcc = $keep($message->getBcc());

        Log::info('mail.suppressed_recipients_dropped', ['count' => count($suppressed), 'subject' => $message->getSubject()]);

        if ($to === [] && $cc === [] && $bcc === []) {
            return false;
        }

        foreach (['To' => $to, 'Cc' => $cc, 'Bcc' => $bcc] as $header => $addresses) {
            $message->getHeaders()->remove($header);
            if ($addresses !== []) {
                $message->getHeaders()->addMailboxListHeader($header, $addresses);
            }
        }

        return null;
    }
}
