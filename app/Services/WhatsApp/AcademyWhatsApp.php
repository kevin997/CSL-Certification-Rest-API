<?php

namespace App\Services\WhatsApp;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Transaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AcademyWhatsApp
{
    public function available(): bool
    {
        return (bool) config('services.academy_whatsapp.enabled')
            && filled(config('services.academy_whatsapp.base_url'))
            && filled(config('services.academy_whatsapp.api_key'));
    }

    public function eligible(Environment $environment): bool
    {
        return $environment->is_active && ! $environment->is_demo
            && EnvironmentLicence::query()->where('environment_id', $environment->id)
                ->whereIn('plan_type', [EnvironmentLicence::PLAN_CREATOR, EnvironmentLicence::PLAN_WHITE_LABEL])
                ->whereIn('status', [
                    EnvironmentLicence::STATUS_CREATOR_ACTIVE,
                    EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE,
                    EnvironmentLicence::STATUS_CANCEL_AT_PERIOD_END,
                ])
                ->whereExists(fn ($transaction) => $transaction->selectRaw('1')->from('transactions')
                    ->whereColumn('transactions.id', 'environment_licences.activated_by_transaction_id')
                    ->where('transactions.status', Transaction::STATUS_COMPLETED)
                    ->where('transactions.amount', '>', 0)
                    ->whereNull('transactions.refunded_at')
                    ->whereNull('transactions.deleted_at'))
                ->where('ends_at', '>', now())
                ->exists();
    }

    public function status(Environment $environment): string
    {
        $response = $this->http()->get($this->sessionUrl($environment));
        if ($response->status() === 404) {
            return 'NOT_CREATED';
        }
        $response->throw();

        if ($response->json('name') !== $this->name($environment)
            || $response->json('config.metadata.kursa_environment_id') !== (string) $environment->id) {
            throw new RuntimeException('Unverified WhatsApp session ownership.');
        }

        $status = $response->json('status');
        if (! in_array($status, ['STOPPED', 'STARTING', 'SCAN_QR_CODE', 'WORKING', 'FAILED', 'PASSKEY_REQUIRED', 'PASSKEY_CONFIRMATION_REQUIRED'], true)) {
            throw new RuntimeException('Unknown WhatsApp session status.');
        }

        return $status;
    }

    public function connect(Environment $environment): string
    {
        $status = $this->status($environment);
        if ($status === 'NOT_CREATED') {
            $this->http()->post($this->base().'/api/sessions', [
                'name' => $this->name($environment),
                'start' => true,
                'config' => ['metadata' => ['kursa_environment_id' => (string) $environment->id]],
            ])->throw();

            return 'STARTING';
        }
        if (in_array($status, ['STOPPED', 'FAILED', 'PASSKEY_REQUIRED', 'PASSKEY_CONFIRMATION_REQUIRED'], true)) {
            $action = $status === 'STOPPED' ? 'start' : 'restart';
            $this->http()->post($this->sessionUrl($environment).'/'.$action)->throw();

            return 'STARTING';
        }

        return $status;
    }

    public function disconnect(Environment $environment): void
    {
        $status = $this->status($environment);
        if ($status === 'NOT_CREATED') {
            return;
        }
        if ($status === 'WORKING') {
            $this->http()->post($this->sessionUrl($environment).'/logout')->throw();
        }
        $this->http()->delete($this->sessionUrl($environment))->throw();
    }

    public function qr(Environment $environment): ?string
    {
        if ($this->status($environment) !== 'SCAN_QR_CODE') {
            return null;
        }

        $response = $this->http()->accept('image/png')->get($this->base().'/api/'.$this->name($environment).'/auth/qr')->throw();
        $image = $response->body();
        if (! str_starts_with($image, "\x89PNG\r\n\x1a\n") || strlen($image) > 250000) {
            throw new RuntimeException('Invalid WhatsApp pairing image.');
        }

        return 'data:image/png;base64,'.base64_encode($image);
    }

    public function pairingCode(Environment $environment, string $phone): ?string
    {
        if ($this->status($environment) !== 'SCAN_QR_CODE') {
            return null;
        }

        $code = $this->http()->post($this->base().'/api/'.$this->name($environment).'/auth/request-code', [
            'phoneNumber' => $phone,
        ])->throw()->json('code');

        if (! is_string($code) || ! preg_match('/^[A-Z0-9]{4}-?[A-Z0-9]{4}$/Di', $code)) {
            throw new RuntimeException('Invalid WhatsApp pairing code.');
        }

        return strtoupper(substr($code, 0, 4).'-'.substr($code, -4));
    }

    /**
     * Null means there is no connected eligible academy sender; only then may
     * callers use the admin sender. Any uncertain response throws instead.
     */
    public function senderFor(Environment $environment): ?string
    {
        return $this->available() && $this->eligible($environment) && $this->status($environment) === 'WORKING'
            ? $this->name($environment)
            : null;
    }

    public function send(Environment $environment, string $session, string $phone, string $message, callable $stillEligible): ?Response
    {
        if ($session !== $this->name($environment)) {
            throw new RuntimeException('Unverified WhatsApp session ownership.');
        }

        $recipient = $this->http()->get($this->base().'/api/contacts/check-exists', [
            'session' => $session,
            'phone' => $phone,
        ])->throw();

        if ($recipient->json('numberExists') === false || ! $stillEligible() || ! $this->eligible($environment)) {
            return null;
        }

        $chatId = $recipient->json('chatId');
        if ($recipient->json('numberExists') !== true
            || ! is_string($chatId) || ! preg_match('/^[0-9]+@(?:c\.us|lid)$/D', $chatId)) {
            throw new RuntimeException('Could not verify WhatsApp recipient.');
        }

        try {
            $response = $this->http()->post($this->base().'/api/sendText', [
                'session' => $session, 'chatId' => $chatId, 'text' => $message,
            ])->throw();
            if (! $response->successful()) {
                throw new RuntimeException('Unexpected WhatsApp send response.');
            }

            return $response;
        } catch (Throwable) {
            throw new AcademyWhatsAppDeliveryUncertain('Academy WhatsApp delivery not confirmed.');
        }
    }

    private function name(Environment $environment): string
    {
        return 'kursa-academy-'.$environment->id;
    }

    private function sessionUrl(Environment $environment): string
    {
        return $this->base().'/api/sessions/'.$this->name($environment);
    }

    private function base(): string
    {
        return rtrim((string) config('services.academy_whatsapp.base_url'), '/');
    }

    private function http(): PendingRequest
    {
        return Http::withHeader('X-Api-Key', (string) config('services.academy_whatsapp.api_key'))
            ->withOptions(['allow_redirects' => false])
            ->acceptJson()->connectTimeout(3)->timeout(8);
    }
}
