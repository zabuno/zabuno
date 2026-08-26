<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * ASVS denetim kaydının kendisini dürüst tutan kapı.
 *
 * Bir güvenlik belgesinin en tehlikeli hâli, atıf verdiği kanıtın artık var
 * olmamasıdır: belge "doğrulandı" demeye devam eder, dosya ise silinmiştir.
 * Bu test, tabloda adı geçen her dosyanın gerçekten var olduğunu doğrular.
 *
 * Requirement ID'leri: ASVS-RECORD-EXISTS-11, ASVS-RECORD-NO-FALSE-CLAIM-12.
 */
final class AsvsAuditRecordTest extends TestCase
{
    private const RECORD = 'security/OWASP-ASVS-BASELINE.md';

    private function record(): string
    {
        return (string) file_get_contents(base_path(self::RECORD));
    }

    // --- ASVS-RECORD-EXISTS-11 --------------------------------------------

    public function test_every_artifact_the_audit_cites_actually_exists(): void
    {
        preg_match_all('/`([A-Za-z0-9_\-\/\.]+\.(?:php|md|json|example|blade\.php))`/', $this->record(), $matches);

        $cited = array_unique($matches[1]);

        self::assertNotEmpty($cited, 'ASVS-RECORD-EXISTS-11: denetim kaydı hiçbir kanıta atıf vermiyor.');

        foreach ($cited as $path) {
            // Yalnız depo içi yolları doğrularız; `.env.production.example`
            // gibi kök dosyalar da buna dâhildir.
            self::assertFileExists(
                base_path($path),
                "ASVS-RECORD-EXISTS-11: denetim kaydı var olmayan `{$path}` dosyasına atıf veriyor."
            );
        }
    }

    // --- ASVS-RECORD-NO-FALSE-CLAIM-12 ------------------------------------

    public function test_the_record_never_claims_certification_or_a_penetration_test(): void
    {
        $record = strtolower($this->record());

        foreach (['asvs certified', 'fully compliant', 'penetration tested', 'pentest passed'] as $overclaim) {
            self::assertStringNotContainsString(
                $overclaim,
                $record,
                "ASVS-RECORD-NO-FALSE-CLAIM-12: \"{$overclaim}\" ifadesi doğru değil ve yanlış güven yaratır."
            );
        }
    }

    public function test_the_record_states_what_it_is_not(): void
    {
        self::assertStringContainsString(
            'Explicit non-claims',
            $this->record(),
            'ASVS-RECORD-NO-FALSE-CLAIM-12: bir güvenlik belgesi neyi KANITLAMADIĞINI da söylemek zorundadır.'
        );
    }
}
