<?php

namespace Tests\Unit;

use App\Services\Marketing\AutomationCopyGenerator;
use RuntimeException;
use Tests\TestCase;

class AutomationCopyGeneratorTest extends TestCase
{
    private AutomationCopyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new AutomationCopyGenerator;
    }

    public function test_it_appends_the_continue_link_when_the_model_omits_it(): void
    {
        $copy = $this->generator->normalize([
            'email_subject' => 'Your order {{order_number}}',
            'email_body' => 'Hi {{name}}, your order is waiting.',
            'whatsapp_template' => 'Hi {{name}}, finish your order here.',
        ]);

        $this->assertStringEndsWith('{{continue_url}}', $copy['email_body']);
        $this->assertStringEndsWith('{{continue_url}}', $copy['whatsapp_template']);
        $this->assertSame('Your order {{order_number}}', $copy['email_subject']);
    }

    public function test_it_keeps_a_continue_link_the_model_already_wrote(): void
    {
        $copy = $this->generator->normalize([
            'email_subject' => 'Pay {{order_number}}',
            'email_body' => 'Continue here: {{continue_url}}',
            'whatsapp_template' => 'Pay here {{continue_url}}',
        ]);

        $this->assertSame(1, substr_count($copy['email_body'], '{{continue_url}}'));
        $this->assertSame(1, substr_count($copy['whatsapp_template'], '{{continue_url}}'));
    }

    public function test_it_cleans_code_fences_and_extra_blank_lines(): void
    {
        $copy = $this->generator->normalize([
            'email_subject' => "```\nYour order\n```",
            'email_body' => "First.\n\n\n\nSecond. {{continue_url}}",
            'whatsapp_template' => 'Hi {{continue_url}}',
        ]);

        $this->assertSame('Your order', $copy['email_subject']);
        $this->assertStringNotContainsString("\n\n\n", $copy['email_body']);
    }

    public function test_it_rejects_an_incomplete_draft(): void
    {
        $this->expectException(RuntimeException::class);

        $this->generator->normalize([
            'email_subject' => 'Only a subject',
            'email_body' => '',
            'whatsapp_template' => '',
        ]);
    }

    public function test_it_clamps_fields_to_the_column_limits(): void
    {
        $copy = $this->generator->normalize([
            'email_subject' => str_repeat('s', 300),
            'email_body' => str_repeat('b', 10050),
            'whatsapp_template' => str_repeat('w', 4050),
        ]);

        $this->assertSame(255, mb_strlen($copy['email_subject']));
        $this->assertSame(10000, mb_strlen($copy['email_body']));
        $this->assertSame(4000, mb_strlen($copy['whatsapp_template']));
    }
}
