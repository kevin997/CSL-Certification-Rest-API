<?php

namespace App\Listeners;

use App\Events\CertificateIssued;
use App\Events\OrderCompleted;
use App\Events\OrderPlaced;
use App\Events\SalesFormSubmitted;
use App\Jobs\RunAcademyWorkflows;

class QueueAcademyWorkflows
{
    public function handleForm(SalesFormSubmitted $event): void
    {
        $this->enqueue('form_submitted', $event->submission);
    }

    public function handleOrder(OrderPlaced $event): void
    {
        $this->enqueue('order_placed', $event->order);
    }

    public function handlePayment(OrderCompleted $event): void
    {
        $this->enqueue('payment_confirmed', $event->order);
    }

    public function handleCertificate(CertificateIssued $event): void
    {
        $this->enqueue('certificate_issued', $event->issuedCertificate);
    }

    private function enqueue(string $trigger, object $object): void
    {
        if ($object->environment_id && $object->id) {
            RunAcademyWorkflows::dispatch(
                $trigger, (int) $object->environment_id, (int) $object->id, (int) now()->format('Uu')
            )->afterCommit();
        }
    }
}
