<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class ContactErrorGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_invalid_field_is_described_linked_and_focused_without_javascript(): void
    {
        foreach (['name', 'email', 'message'] as $field) {
            $xpath = $this->renderErrors([$field => 'Bu alanı düzeltin.']);
            $input = $xpath->query('//*[@id="contact-'.$field.'"]')->item(0);
            self::assertSame('true', $input->getAttribute('aria-invalid'));
            self::assertSame('contact-'.$field.'-error', $input->getAttribute('aria-describedby'));
            self::assertSame(1, $xpath->query('//*[@autofocus]')->length);
            self::assertTrue($input->hasAttribute('autofocus'));
            self::assertSame('Bu alanı düzeltin.', $xpath->query('//*[@id="contact-'.$field.'-error"]')->item(0)->textContent);
            self::assertSame(1, $xpath->query('//a[@href="#contact-'.$field.'"]')->length);
            self::assertSame(1, $xpath->query('//*[@aria-invalid="true"]')->length);
        }
    }

    public function test_multiple_errors_focus_only_the_first_visible_field_and_preserve_escaped_input(): void
    {
        $xpath = $this->renderErrors(['message' => '<script>alert(1)</script>', 'email' => 'E-postayı düzeltin.', 'name' => 'Adı düzeltin.', 'website' => 'Hidden trap'], [
            'name' => ' Ada ', 'email' => 'ada@example.test', 'message' => '<b>Mesajım</b>',
        ]);
        self::assertSame(1, $xpath->query('//*[@autofocus]')->length);
        self::assertSame('contact-name', $xpath->query('//*[@autofocus]')->item(0)->getAttribute('id'));
        self::assertSame(' Ada ', $xpath->query('//*[@id="contact-name"]')->item(0)->getAttribute('value'));
        self::assertSame('ada@example.test', $xpath->query('//*[@id="contact-email"]')->item(0)->getAttribute('value'));
        self::assertSame('<b>Mesajım</b>', $xpath->query('//*[@id="contact-message"]')->item(0)->textContent);
        self::assertSame('<script>alert(1)</script>', $xpath->query('//*[@id="contact-message-error"]')->item(0)->textContent);
        self::assertSame(0, $xpath->query('//*[@id="contact-message-error"]//script')->length);
        self::assertSame(0, $xpath->query('//a[@href="#contact-website"]')->length);
        self::assertFalse($xpath->query('//*[@id="contact-website"]')->item(0)->hasAttribute('aria-invalid'));
    }

    public function test_a_fresh_form_has_no_error_state_or_automatic_focus(): void
    {
        $xpath = $this->renderErrors([]);
        self::assertSame(0, $xpath->query('//*[@autofocus or @aria-invalid or @aria-describedby]')->length);
    }

    public function test_a_whitespace_name_post_returns_to_the_field_without_losing_the_message(): void
    {
        $response = $this->from('/contact')->followingRedirects()->post('/contact', [
            'name' => '   ', 'email' => 'ada@example.test', 'message' => 'Menümü aktaramadım, yardım eder misiniz?',
        ])->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($dom);
        self::assertSame('contact-name', $xpath->query('//*[@autofocus]')->item(0)->getAttribute('id'));
        self::assertSame(1, $xpath->query('//*[@autofocus]')->length);
        self::assertSame('contact-name-error', $xpath->query('//*[@id="contact-name"]')->item(0)->getAttribute('aria-describedby'));
        self::assertSame('Menümü aktaramadım, yardım eder misiniz?', $xpath->query('//*[@id="contact-message"]')->item(0)->textContent);
        $this->assertDatabaseCount('support_requests', 0);
    }

    private function renderErrors(array $messages, array $input = []): DOMXPath
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag($messages));
        $this->withSession(['errors' => $errors, '_old_input' => $input]);
        $this->app['session']->save();
        $response = $this->withCookie(config('session.cookie'), $this->app['session']->getId())->get('/contact')->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        return new DOMXPath($dom);
    }
}
