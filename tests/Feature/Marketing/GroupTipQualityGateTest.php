<?php

namespace Tests\Feature\Marketing;

use App\Console\Commands\GenerateMarketingContentCommand;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Group tips are broadcast to the customer WhatsApp group with no human review
 * in between, so degenerate model output has to be rejected at generation time.
 *
 * The bad strings below are verbatim from production marketing_messages rows.
 */
class GroupTipQualityGateTest extends TestCase
{
    private function reject(string $text): ?string
    {
        $method = new ReflectionMethod(GenerateMarketingContentCommand::class, 'rejectionReason');

        return $method->invoke(app(GenerateMarketingContentCommand::class), $text);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function degenerateOutputProvider(): array
    {
        return [
            // marketing_messages id=36, verbatim.
            'mojibake registered sign' => ['®dutilisation rapide des cycles de déploiement individuels pour les équipes indépendantes'],
            'emoji and mojibake mid sentence' => ['😊Ç½ deploy independent teams quickly'],
            'replacement character' => ["Gérer les paiements par mobile\u{FFFD}monnaie et autres réseaux"],
            'vulgar fraction' => ['Gérez vos paiements ½ prix pour les instructeurs Ç½ aujourd hui'],
            'too short' => ['Payez vite'],
            'control character' => ["Gérer les paiements\x07 par mobile money pour vos apprenants"],
            'unpronounceable run' => ['Gérez vos paiements avec zzxcvbnm rapidement et simplement'],
        ];
    }

    /**
     * @dataProvider degenerateOutputProvider
     */
    public function test_degenerate_output_is_rejected(string $text): void
    {
        $this->assertNotNull(
            $this->reject($text),
            "this should have been rejected but passed the gate: {$text}"
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function acceptableOutputProvider(): array
    {
        return [
            'french with accents' => ['Créez et vendez vos formations en ligne, avec certificats vérifiables à la clé.'],
            'english' => ['Accept payments via Monetbil, TaraMoney and Stripe — no card required to start.'],
            'punctuation and symbols' => ['Lancez votre académie (100% à votre marque) : domaine, logo & couleurs.'],
            'curly apostrophe' => ['Suivez l’engagement de vos apprenants et repérez ceux qui décrochent.'],
        ];
    }

    /**
     * @dataProvider acceptableOutputProvider
     */
    public function test_legitimate_copy_passes(string $text): void
    {
        $this->assertNull(
            $this->reject($text),
            "this is valid copy but was rejected as: ".($this->reject($text) ?? '')
        );
    }
}
