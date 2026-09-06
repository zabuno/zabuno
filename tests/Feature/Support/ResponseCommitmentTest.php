<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Mail\SupportRequestAcknowledged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FF-201 RED — yanıt taahhüdü UYDURULMAZ (`docs/125` §3).
 *
 * Taahhüdün sayısı SAHİBİN kararıdır ve bugün verilmiş değil. Yapılandırma
 * yokken hiçbir yüzey bir saat sayısı yazmaz; varken üç yüzey (iletişim
 * sayfası, alındı e-postası, panel destek sayfası) AYNI cümleyi TEK
 * kaynaktan gösterir. İki yerde iki farklı sayı, birinin yalan olduğu gün
 * fark edilir — o gün müşteri "ama sitede 24 yazıyordu" der.
 *
 * Requirement IDs: SUPPORT-COMMITMENT-ABSENT-01, SUPPORT-COMMITMENT-SINGLE-SOURCE-01,
 * SUPPORT-COMMITMENT-INVALID-IS-ABSENT-01.
 */
final class ResponseCommitmentTest extends TestCase
{
    use RefreshDatabase;

    private const SENTENCE_WITH_24 = 'within 24 hours';

    private function verifiedOwner(): array
    {
        $owner = User::factory()->create([
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse-commit@example.test',
            'email_verified_at' => now(),
        ]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Kebap',
            'slug' => 'zeytin-kebap-commit',
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$owner, $workspaceId];
    }

    // --- SUPPORT-COMMITMENT-ABSENT-01 -------------------------------------

    public function test_with_no_configured_commitment_no_surface_promises_a_response_time(): void
    {
        Mail::fake();
        config(['support.response_commitment_hours' => null]);

        // İletişim sayfası.
        $page = (string) $this->get('/contact')->getContent();
        self::assertDoesNotMatchRegularExpression('/within \d+ hours/i', $page, 'SUPPORT-COMMITMENT-ABSENT-01: sayfa taahhüt uydurmamalı.');

        // Alındı e-postası.
        $this->post('/contact', [
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'message' => 'Menüm görünmüyor.',
        ])->assertRedirect();

        Mail::assertSent(SupportRequestAcknowledged::class, function (SupportRequestAcknowledged $mail): bool {
            return preg_match('/within \d+ hours/i', $mail->render()) !== 1;
        });

        // Panel.
        [$owner, $workspaceId] = $this->verifiedOwner();

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/support-requests");

        $response->assertOk();
        self::assertNull($response->json('commitment'), 'SUPPORT-COMMITMENT-ABSENT-01: panel de taahhüt uydurmamalı.');
    }

    // --- SUPPORT-COMMITMENT-SINGLE-SOURCE-01 ------------------------------

    public function test_with_a_configured_commitment_all_three_surfaces_show_the_same_sentence(): void
    {
        Mail::fake();
        config(['support.response_commitment_hours' => 24]);

        $page = (string) $this->get('/contact')->getContent();
        self::assertStringContainsString(self::SENTENCE_WITH_24, $page);

        preg_match('/[^<>]*within 24 hours[^<>]*/', $page, $match);
        $pageSentence = trim($match[0] ?? '');
        self::assertNotSame('', $pageSentence);

        $this->post('/contact', [
            'name' => 'Hüseyin',
            'email' => 'huseyin@example.com',
            'message' => 'Menüm görünmüyor.',
        ])->assertRedirect();

        Mail::assertSent(SupportRequestAcknowledged::class, function (SupportRequestAcknowledged $mail) use ($pageSentence): bool {
            return str_contains($mail->render(), $pageSentence);
        });

        [$owner, $workspaceId] = $this->verifiedOwner();

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/support-requests");

        $response->assertOk();
        self::assertSame(24, $response->json('commitment.hours'));
        self::assertSame($pageSentence, $response->json('commitment.sentence'), 'SUPPORT-COMMITMENT-SINGLE-SOURCE-01: panel cümlesi sayfanınkiyle aynı olmalı.');
    }

    // --- SUPPORT-COMMITMENT-INVALID-IS-ABSENT-01 --------------------------

    public function test_a_non_positive_or_non_numeric_value_is_treated_as_no_commitment(): void
    {
        foreach (['0', '-3', 'soon', ''] as $value) {
            config(['support.response_commitment_hours' => $value]);

            $page = (string) $this->get('/contact')->getContent();

            self::assertDoesNotMatchRegularExpression(
                '/within [^<]*hours/i',
                $page,
                "SUPPORT-COMMITMENT-INVALID-IS-ABSENT-01: `{$value}` bir taahhüt değildir."
            );
        }
    }
}
