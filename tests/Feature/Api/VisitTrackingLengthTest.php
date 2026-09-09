<?php

namespace Tests\Feature\Api;

use App\Models\AcademyVisitEvent;
use App\Models\AcademyVisitor;
use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A visitor is recorded whatever their browser calls itself.
 *
 * Every error this API logged over two days was the same one, ninety-three of
 * them: SQLSTATE[22001] "Data too long for column 'user_agent'". The columns are
 * string() -- varchar(1024) -- and the values are request headers, which nobody
 * bounds. Facebook's in-app browser sends close to three hundred characters, so
 * every visitor arriving from a Facebook link 500s instead of being counted.
 *
 * The endpoint already refuses a visit_hash over 200 characters, so length was
 * considered for one field of five and not for the others.
 *
 * These tests assert the truncation rather than the database's refusal, on
 * purpose: the suite runs on SQLite, which does not enforce varchar length at
 * all. A test that simply posted a long header would pass here while production
 * kept failing -- which is exactly how this survived. What is checked is that
 * nothing longer than the column can leave this controller.
 */
class VisitTrackingLengthTest extends TestCase
{
    use RefreshDatabase;

    /** The Facebook in-app browser user agent from the production logs. */
    private const FACEBOOK_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_5 like Mac OS X) AppleWebKit/605.1.15 '
        .'(KHTML, like Gecko) Mobile/23F77 Safari/604.1 [FBAN/FBIOS;FBAV/558.0.0.58.74;FBBV/948561885;'
        .'FBDV/iPhone16,1;FBMD/iPhone;FBSN/iOS;FBSV/26.5;FBSS/3;FBID/phone;FBLC/fr_FR;FBOP/5;'
        .'FBRV/957194181;IABMV/1];FBNV/1';

    private function environment(): Environment
    {
        Cache::flush();

        return Environment::factory()->create([
            'primary_domain' => 'bootcamps.test',
            'is_active' => true,
        ]);
    }

    private function track(Environment $environment, array $headers = [], array $body = [])
    {
        // The environment is put in the session rather than resolved from the
        // Host header: DetectEnvironment reads the real hostname, which the test
        // client does not let us set convincingly, and getEnvironmentId accepts
        // either. What is under test is what the controller stores, not how the
        // tenant was found.
        return $this->withSession(['current_environment_id' => $environment->id])
            ->withHeaders($headers)
            ->postJson('/api/analytics/visits/track', array_merge([
                'visit_hash' => 'abc123',
            ], $body));
    }

    public function test_a_visitor_using_the_facebook_browser_is_recorded(): void
    {
        $environment = $this->environment();

        $response = $this->track($environment, ['User-Agent' => self::FACEBOOK_UA]);

        $response->assertSuccessful();
        $this->assertSame(1, AcademyVisitor::where('environment_id', $environment->id)->count());
    }

    public function test_a_user_agent_is_never_stored_longer_than_its_column(): void
    {
        $environment = $this->environment();

        $this->track($environment, ['User-Agent' => str_repeat('u', 4000)]);

        $visitor = AcademyVisitor::where('environment_id', $environment->id)->firstOrFail();
        $this->assertLessThanOrEqual(1024, mb_strlen((string) $visitor->user_agent));
    }

    public function test_every_client_supplied_string_is_bounded_not_only_the_user_agent(): void
    {
        // path and referrer come from the request body and accept_language from a
        // header; all three are varchar(1024) and none was checked. A long Referer
        // would have failed the same way the moment somebody sent one.
        $environment = $this->environment();

        $this->track(
            $environment,
            ['User-Agent' => self::FACEBOOK_UA, 'Accept-Language' => str_repeat('fr-FR,', 500)],
            ['path' => '/'.str_repeat('p', 4000), 'referrer' => 'https://x.test/'.str_repeat('r', 4000)]
        );

        $visitor = AcademyVisitor::where('environment_id', $environment->id)->firstOrFail();
        $this->assertLessThanOrEqual(1024, mb_strlen((string) $visitor->accept_language));

        $event = AcademyVisitEvent::where('environment_id', $environment->id)->firstOrFail();
        $this->assertLessThanOrEqual(1024, mb_strlen((string) $event->path));
        $this->assertLessThanOrEqual(1024, mb_strlen((string) $event->referrer));
        $this->assertLessThanOrEqual(1024, mb_strlen((string) $event->user_agent));
    }

    public function test_a_short_user_agent_is_stored_as_it_came(): void
    {
        $environment = $this->environment();

        $this->track($environment, ['User-Agent' => 'Mozilla/5.0 (probe)']);

        $this->assertSame(
            'Mozilla/5.0 (probe)',
            AcademyVisitor::where('environment_id', $environment->id)->firstOrFail()->user_agent
        );
    }
}
