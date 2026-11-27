<?php

namespace Tests\Feature\Api;

use App\Models\AcademyWorkflow;
use App\Models\Environment;
use App\Models\MarketingAutomation;
use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['licensing.enforcement_enabled' => false]);
    }

    private function definition(string $name = 'New workflow'): array
    {
        return ['name' => $name, 'trigger' => 'form_submitted', 'filter_id' => null, 'actions' => [
            ['channel' => 'in_app', 'recipient' => 'owner', 'title' => 'New form', 'message' => 'Form: {{reference}}'],
        ]];
    }

    public function test_separate_drafts_simulation_and_published_revision(): void
    {
        Mail::fake();
        Http::fake();
        Queue::fake();
        $owner = User::factory()->create();
        Environment::factory()->create(['owner_id' => $owner->id, 'primary_domain' => 'workflow.example']);
        Sanctum::actingAs($owner);
        $this->withHeader('X-Frontend-Domain', 'workflow.example');
        $first = $this->postJson('/api/academy-workflows', $this->definition())->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $this->postJson('/api/academy-workflows', $this->definition('Another workflow'))->assertCreated();
        $this->postJson('/api/academy-workflows/simulate', $this->definition())->assertOk()
            ->assertJsonPath('data.simulation', true)->assertJsonPath('data.actions.0.message', 'Form: Example form');
        $this->assertDatabaseCount('academy_workflow_runs', 0);
        $this->postJson("/api/academy-workflows/{$first}/publish")->assertOk()->assertJsonPath('data.status', 'active');
        $this->putJson("/api/academy-workflows/{$first}", $this->definition('Draft name'))->assertOk()
            ->assertJsonPath('data.draft.name', 'Draft name')->assertJsonPath('data.published.name', 'New workflow');
        $this->postJson("/api/academy-workflows/{$first}/pause")->assertOk()->assertJsonPath('data.status', 'paused');
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
    }

    public function test_other_academy_cannot_access_workflow_or_submit_tenant_override(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $first = Environment::factory()->create(['owner_id' => $owner->id, 'primary_domain' => 'workflow.example']);
        Environment::factory()->create(['owner_id' => $otherOwner->id, 'primary_domain' => 'other.example']);
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/academy-workflows', $this->definition(), ['X-Frontend-Domain' => 'workflow.example'])->assertCreated()->json('data.id');
        Sanctum::actingAs($otherOwner);
        $headers = ['X-Frontend-Domain' => 'other.example'];
        $this->postJson("/api/academy-workflows/{$id}/publish", [], $headers)->assertNotFound();
        $this->getJson("/api/academy-workflows/{$id}/runs", $headers)->assertNotFound();
        $this->postJson('/api/academy-workflows', $this->definition() + ['environment_id' => $first->id], $headers)->assertUnprocessable();
        $this->postJson('/api/academy-workflows', $this->definition(), ['X-Frontend-Domain' => 'workflow.example'])->assertForbidden();
        $unsafe = $this->definition();
        $unsafe['actions'][0]['address'] = 'unspecified@example.test';
        $this->postJson('/api/academy-workflows', $unsafe, $headers)->assertUnprocessable();
    }

    public function test_matching_events_use_the_published_version_and_do_not_duplicate(): void
    {
        $owner = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        $form = $this->form($academy, $owner);
        $other = $this->form($academy, $owner);
        $existing = $this->submission($academy, $form);
        $this->travel(1)->minute();
        $definition = $this->definition();
        $definition['filter_id'] = $form->id;
        $first = $this->active($academy, $definition);
        $this->active($academy, $definition);
        $first->update(['draft' => $this->definition('Unpublished edit')]);
        $this->travel(1)->minute();
        $match = $this->submission($academy, $form);
        $different = $this->submission($academy, $other);
        $runner = app(WorkflowRunner::class);

        $runner->run('form_submitted', $academy->id, $existing->id);
        $runner->run('form_submitted', $academy->id + 100, $match->id);
        $runner->run('form_submitted', $academy->id, $different->id);
        $runner->run('form_submitted', $academy->id, $match->id);
        $runner->run('form_submitted', $academy->id, $match->id);

        $this->assertDatabaseHas('user_notifications', [
            'environment_id' => $academy->id, 'user_id' => $owner->id, 'message' => 'Form: '.$form->title,
        ]);
        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertDatabaseCount('academy_workflow_runs', 4);
        $this->assertDatabaseHas('academy_workflow_runs', ['state' => 'skipped', 'reason' => 'duplicate']);
        $this->assertDatabaseHas('academy_workflow_runs', ['state' => 'skipped', 'reason' => 'condition_not_met']);
        AcademyWorkflow::where('environment_id', $academy->id)->update(['active' => false]);
        $runner->run('form_submitted', $academy->id, $this->submission($academy, $form)->id);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_cancelled_consent_and_system_channel_overlap_never_send(): void
    {
        Http::fake();
        Mail::fake();
        config()->set('services.waha', ['base_url' => 'https://waha.test', 'api_key' => 'test-key', 'session' => 'test']);
        $owner = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        $form = $this->form($academy, $owner);
        $definition = $this->definition();
        $definition['actions'][0] = ['channel' => 'whatsapp', 'recipient' => 'subject', 'title' => '', 'message' => 'Example'];
        $this->active($academy, $definition);
        $this->travel(1)->minute();
        $submission = $this->submission($academy, $form);
        MarketingConsent::grant($submission, 'whatsapp', 'test', '2026-09', now());
        MarketingConsent::revoke($submission, 'whatsapp', 'test', '2026-09', now());
        app(WorkflowRunner::class)->run('form_submitted', $academy->id, $submission->id);
        $this->assertDatabaseHas('academy_workflow_runs', ['reason' => 'consent_required']);

        MarketingAutomation::create(['environment_id' => $academy->id, 'trigger' => 'form_submitted',
            'enabled' => true, 'recipient' => 'customer', 'channels' => ['whatsapp']]);
        $overlap = $this->submission($academy, $form);
        MarketingConsent::grant($overlap, 'whatsapp', 'test', '2026-09', now());
        app(WorkflowRunner::class)->run('form_submitted', $academy->id, $overlap->id);
        $this->assertDatabaseHas('academy_workflow_runs', ['reason' => 'system_workflow_overlap']);
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_payment_trigger_requires_a_verified_positive_provider_transaction(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        $definition = $this->definition();
        $definition['trigger'] = 'payment_confirmed';
        $this->active($academy, $definition);
        $this->travel(1)->minute();
        $order = Order::withoutGlobalScopes()->create([
            'environment_id' => $academy->id, 'user_id' => $buyer->id, 'order_number' => 'ORD-TEST',
            'total_amount' => 5000, 'currency' => 'XAF', 'status' => Order::STATUS_COMPLETED, 'type' => Order::TYPE_STOREFRONT,
        ]);
        $runner = app(WorkflowRunner::class);
        $runner->run('payment_confirmed', $academy->id, $order->id);
        $this->assertDatabaseCount('user_notifications', 0);
        $transaction = Transaction::withoutGlobalScopes()->create([
            'environment_id' => $academy->id, 'order_id' => $order->id, 'customer_id' => $buyer->id,
            'transaction_id' => 'workflow-transaction', 'amount' => 5000, 'total_amount' => 5000, 'currency' => 'XAF',
            'status' => Transaction::STATUS_COMPLETED, 'purpose' => Transaction::PURPOSE_COURSE_SALE,
            'paid_at' => now(), 'gateway_transaction_id' => 'test-gateway-reference',
        ]);
        $runner->run('payment_confirmed', $academy->id, $order->id);
        $this->assertDatabaseCount('user_notifications', 0);
        $transaction->update(['verified_at' => now()]);
        $runner->run('payment_confirmed', $academy->id, $order->id);
        $runner->run('payment_confirmed', $academy->id, $order->id);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_verified_whatsapp_is_submitted_once_and_consent_is_rechecked_after_lookup(): void
    {
        config()->set('services.waha', ['base_url' => 'https://waha.test', 'api_key' => 'test-key', 'session' => 'test']);
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        $form = $this->form($academy, $owner);
        $definition = $this->definition();
        $definition['actions'][0] = ['channel' => 'whatsapp', 'recipient' => 'subject', 'title' => '', 'message' => 'Hello {{name}}'];
        $this->active($academy, $definition);
        $this->travel(1)->minute();
        $allowed = $this->submission($academy, $form);
        $revoked = $this->submission($academy, $form);
        MarketingConsent::grant($allowed, 'whatsapp', 'test', '2026-09', now());
        MarketingConsent::grant($revoked, 'whatsapp', 'test', '2026-09', now());
        $lookups = 0;
        Http::fake([
            'https://waha.test/api/sessions/test' => Http::response(['status' => 'WORKING']),
            'https://waha.test/api/contacts/check-exists*' => function () use (&$lookups, $revoked) {
                if (++$lookups === 2) {
                    MarketingConsent::revoke($revoked, 'whatsapp', 'test', '2026-09', now());
                }

                return Http::response(['numberExists' => true, 'chatId' => '123456789@lid']);
            },
            'https://waha.test/api/sendText' => Http::response(['id' => 'mock-message']),
        ]);
        $runner = app(WorkflowRunner::class);
        $runner->run('form_submitted', $academy->id, $allowed->id);
        $runner->run('form_submitted', $academy->id, $allowed->id);
        $runner->run('form_submitted', $academy->id, $revoked->id);
        Http::assertSentCount(5);
        Http::assertSent(fn ($request) => $request->url() === 'https://waha.test/api/sendText'
            && $request['chatId'] === '123456789@lid' && $request['text'] === 'Hello Test subject');
        $this->assertDatabaseHas('academy_workflow_runs', ['state' => 'accepted']);
        $this->assertDatabaseHas('academy_workflow_runs', ['state' => 'skipped', 'reason' => 'recipient_or_eligibility']);
    }

    public function test_event_observed_before_publication_is_not_replayed_in_the_same_second(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        $workflow = $this->active($academy, $this->definition());
        $boundary = (int) now()->format('Uu');
        $workflow->update(['activated_at_us' => $boundary]);
        $submission = $this->submission($academy, $this->form($academy, $owner));
        $runner = app(WorkflowRunner::class);

        $runner->run('form_submitted', $academy->id, $submission->id, $boundary - 1);
        $this->assertDatabaseCount('user_notifications', 0);
        $runner->run('form_submitted', $academy->id, $submission->id, $boundary + 1);
        $this->assertDatabaseCount('user_notifications', 1);
    }

    private function active(Environment $academy, array $definition): AcademyWorkflow
    {
        return AcademyWorkflow::create([
            'environment_id' => $academy->id, 'draft' => $definition, 'published' => $definition,
            'published_trigger' => $definition['trigger'], 'revision' => 1, 'active' => true, 'published_at' => now(),
        ]);
    }

    private function form(Environment $academy, User $owner): SalesForm
    {
        return SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $academy->id, 'created_by' => $owner->id,
            'title' => 'Example marketing form', 'slug' => 'fixture-'.str()->uuid(), 'status' => SalesForm::STATUS_PUBLISHED,
        ]);
    }

    private function submission(Environment $academy, SalesForm $form): SalesFormSubmission
    {
        return SalesFormSubmission::withoutGlobalScopes()->create([
            'environment_id' => $academy->id, 'sales_form_id' => $form->id, 'access_code' => str()->random(8),
            'user_id' => User::factory()->create()->id,
            'name' => 'Test subject', 'email' => 'subject@example.test', 'phone' => '+237699000001',
            'answers' => [], 'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }
}
