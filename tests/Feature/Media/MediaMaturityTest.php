<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Port\MediaEvidencePort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * OLGUNLUK — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Olgunluk"`; seviye sözlüğü `docs/108` §6.7
 * (L0 Yok · L1 Çalışıyor · L2 Güvenli · L3 Ölçülüyor · L4 Olgun).
 *
 * ═══ BU DOSYANIN KORUDUĞU TEK ŞEY: KANITSIZ PUAN YASAK ═══
 *
 * Bir olgunluk ekranı, ürünün KENDİSİ hakkında konuştuğu tek ekrandır ve
 * tam da bu yüzden en kolay yalan söyleyebileceği yerdir. Elle yazılmış
 * bir "L4" hiçbir şeyin kanıtı değildir: onu yazan kişi o gün iyimserse
 * ekran iyimser olur, ve sahip ürünün yapamadığı bir şeye güvenir.
 *
 * O yüzden seviye bir TABLO değil bir HESAPTIR. Her basamak, bu depoda
 * GERÇEKTEN sorgulanabilir bir kanıta bağlıdır:
 *
 *   - L1 "çalışıyor"  → o yeteneğin KAYITLI bir ucu var mı
 *                       (`Route` koleksiyonuna sorulur).
 *   - L2 "güvenli"    → hata ve kısıt durumunun ADLANDIRILMIŞ bir testi
 *                       var mı (gereksinim kimliği ya da test yöntemi adı;
 *                       `tests/Feature/Media` içinde aranır).
 *   - L3 "ölçülüyor"  → denetim izi ya da sayaç üretiyor mu (yine bir uç
 *                       ya da adlandırılmış bir test).
 *   - L4 "olgun"      → kendini onarıyor (yeniden deneme/kuyruk) VE
 *                       kullanıcıya anlatıyor mu.
 *
 * ÜÇ DEĞİŞMEZ:
 *
 *   1. Kanıtı OLMAYAN bir basamak ASLA geçilmiş sayılmaz. Boş kanıt
 *      listesi "belki olmuştur" değil, "hayır"dır.
 *   2. Basamaklar ARDIŞIKTIR. L2 düşmüşken L3'ün kanıtı bulunsa bile
 *      seviye L1'de kalır — "ölçülüyor ama güvenli değil" bir olgunluk
 *      derecesi değil, bir çelişkidir.
 *   3. Kanıt DENETLENEMİYORSA (ör. dağıtımda `tests/` klasörü yok) basamak
 *      geçilmiş SAYILMAZ; ekran "denetlenemedi" der. Göremediğimiz bir şeyi
 *      "geçti" diye geçirmiyoruz — PDF denetçisindeki `/ObjStm` kararıyla
 *      aynı yön (`docs/108` §6.2).
 *
 * Gereksinim: MEDIA-MATURITY-EVIDENCE-REAL-01,
 * MEDIA-MATURITY-NO-EVIDENCE-NO-LEVEL-02, MEDIA-MATURITY-CONSECUTIVE-03,
 * MEDIA-MATURITY-UNVERIFIABLE-04, MEDIA-MATURITY-SELF-ASSESSED-05,
 * MEDIA-MATURITY-TENANT-06.
 */
final class MediaMaturityTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function ownerWorkspace(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slug,
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

        return $workspaceId;
    }

    /**
     * Kanıt çözücünün YERİNE geçen ikiz.
     *
     * Gerçeği değil, VERİLEN cevabı döner: böylece "kanıt bulunamazsa ne
     * olur" ve "kanıt denetlenemezse ne olur" soruları, depoyu bozmadan
     * sorulabilir.
     *
     * @param  array<string, bool|null>  $overrides  kanıt referansı → cevap
     */
    private function fakeEvidence(?bool $default, array $overrides = []): MediaEvidencePort
    {
        return new class($default, $overrides) implements MediaEvidencePort
        {
            /** @param array<string, bool|null> $overrides */
            public function __construct(
                private readonly ?bool $default,
                private readonly array $overrides,
            ) {}

            public function hasEndpoint(string $method, string $uri): bool
            {
                return $this->answer($method.' '.$uri) === true;
            }

            public function hasRequirement(string $requirementId): ?bool
            {
                return $this->answer($requirementId);
            }

            public function hasTestMethod(string $class, string $method): ?bool
            {
                return $this->answer($class.'::'.$method);
            }

            private function answer(string $ref): ?bool
            {
                return array_key_exists($ref, $this->overrides)
                    ? $this->overrides[$ref]
                    : $this->default;
            }
        };
    }

    /** @return array<string, array<string, mixed>> */
    private function capabilitiesByKey(mixed $capabilities): array
    {
        $map = [];

        foreach ((array) $capabilities as $row) {
            $map[(string) $row['key']] = $row;
        }

        return $map;
    }

    // --- MEDIA-MATURITY-EVIDENCE-REAL-01 -----------------------------------

    /**
     * EKRANIN GÖSTERDİĞİ HER KANIT BU DEPODA GERÇEKTEN VARDIR.
     *
     * Bu test bilerek kırılgandır ve kırılganlığı onun İŞİDİR. Biri bir
     * ucu kaldırırsa ya da bir testin adını değiştirirse, olgunluk ekranı
     * o an artık var olmayan bir kanıta dayanıyor demektir. O ekran
     * sessizce yanlış olmaktansa burada gürültüyle kırılmalı.
     *
     * Restoran sahibinin yolculuğu: panelde "Yükleme · L4" yazısını
     * okuyup dosya yüklemeye güveniyor. Bu satırın altında yazan kanıtın
     * karşılığı depoda yoksa, o güven bir yalana dayanıyordur.
     */
    public function test_every_evidence_the_screen_shows_really_exists_in_this_repository(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'olgunluk-gercek');

        $response = $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/media/maturity");

        $response->assertOk('MEDIA-MATURITY-EVIDENCE-REAL-01: olgunluk ucu okunabilmeli.');

        $absent = [];

        foreach ((array) $response->json('capabilities') as $capability) {
            foreach ((array) $capability['rungs'] as $rung) {
                foreach ((array) $rung['evidence'] as $evidence) {
                    if ($evidence['state'] !== 'found') {
                        $absent[] = $capability['key'].' L'.$rung['level'].' → '.$evidence['ref'].' ('.$evidence['state'].')';
                    }
                }
            }
        }

        self::assertSame(
            [],
            $absent,
            'MEDIA-MATURITY-EVIDENCE-REAL-01: ekran, bu depoda karşılığı OLMAYAN bir kanıt gösteremez.',
        );
    }

    // --- MEDIA-MATURITY-NO-EVIDENCE-NO-LEVEL-02 ----------------------------

    /**
     * KANITI OLMAYAN BASAMAK, HER ŞEYE "EVET" DİYEN BİR DÜNYADA BİLE GEÇİLMEZ.
     *
     * İkiz burada sorulan HER kanıta "var" diyor. Buna rağmen bazı
     * yetenekler L4'e çıkmıyor — çünkü o basamaklar için depoda gösterecek
     * bir kanıt HİÇ TANIMLANMAMIŞ. Boş bir kanıt listesi bir puan değil,
     * bir eksikliktir.
     *
     * Somut örnek: virüs taraması bu depoda çalışıyor ve enfekte dosyanın
     * reddi testli — ama taramanın bir SAYACI yok. "Ölçülüyor" diyemeyiz.
     */
    public function test_a_rung_with_no_evidence_is_never_met_even_when_everything_else_says_yes(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'olgunluk-kanitsiz');

        $this->app->instance(MediaEvidencePort::class, $this->fakeEvidence(true));

        $response = $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/media/maturity");

        $capabilities = $this->capabilitiesByKey($response->json('capabilities'));

        self::assertArrayHasKey('scan', $capabilities, 'MEDIA-MATURITY-NO-EVIDENCE-NO-LEVEL-02: tarama satırı çizilmeli.');
        self::assertSame(
            2,
            $capabilities['scan']['level'],
            'MEDIA-MATURITY-NO-EVIDENCE-NO-LEVEL-02: taramanın sayacı yok; her kanıt "var" dese bile L2\'de kalmalı.',
        );

        $third = $capabilities['scan']['rungs'][2];

        self::assertSame([], $third['evidence'], 'MEDIA-MATURITY-NO-EVIDENCE-NO-LEVEL-02: L3 için tanımlı kanıt olmamalı.');
        self::assertSame('unmet', $third['state'], 'MEDIA-MATURITY-NO-EVIDENCE-NO-LEVEL-02: kanıtsız basamak geçilmiş sayılamaz.');
    }

    // --- MEDIA-MATURITY-CONSECUTIVE-03 -------------------------------------

    /**
     * ARDIŞIKLIK: alt basamak düştüyse üsttekinin kanıtı sayılmaz.
     *
     * "Ölçülüyor ama güvenli değil" bir olgunluk derecesi değildir. Bir
     * yükleme ucu sayı üretiyor olabilir; boyut sınırının testi yoksa o
     * sayı yalnız kaç dosyanın sessizce kabul edildiğini sayar.
     */
    public function test_a_broken_lower_rung_stops_the_level_even_if_higher_evidence_exists(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'olgunluk-ardisik');

        $this->app->instance(MediaEvidencePort::class, $this->fakeEvidence(true, [
            'MEDIA-INTAKE-SIZE-REJECT-01' => false,
        ]));

        $response = $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/media/maturity");

        $capabilities = $this->capabilitiesByKey($response->json('capabilities'));

        self::assertSame(
            1,
            $capabilities['intake']['level'],
            'MEDIA-MATURITY-CONSECUTIVE-03: L2 düştüyse seviye L1\'de kalmalı.',
        );
        self::assertSame(
            'met',
            $capabilities['intake']['rungs'][2]['state'],
            'MEDIA-MATURITY-CONSECUTIVE-03: üst basamağın kanıtı yine de DÜRÜSTÇE bildirilir; yalnız seviyeye SAYILMAZ.',
        );
    }

    // --- MEDIA-MATURITY-UNVERIFIABLE-04 ------------------------------------

    /**
     * DENETLENEMEYEN KANIT "GEÇTİ" DEĞİLDİR.
     *
     * Dağıtılmış bir sunucuda `tests/` klasörü bulunmayabilir. O zaman
     * doğru cevap "L4" değil, "buradan bakınca göremiyorum"dur. Göremediği
     * şeyi geçmiş sayan bir ekran, en çok ihtiyaç duyulan yerde en cesur
     * yalanı söyler.
     */
    public function test_evidence_that_cannot_be_checked_is_reported_as_such_and_never_counted(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'olgunluk-denetlenemez');

        // Uçlar kayıtlı (L1 görülebiliyor); test paketi ise okunamıyor.
        $this->app->instance(MediaEvidencePort::class, $this->fakeEvidence(null, [
            'POST api/workspaces/{workspace}/media' => true,
        ]));

        $response = $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/media/maturity");

        $capabilities = $this->capabilitiesByKey($response->json('capabilities'));
        $intake = $capabilities['intake'];

        self::assertSame(1, $intake['level'], 'MEDIA-MATURITY-UNVERIFIABLE-04: denetlenemeyen basamak seviyeye sayılmaz.');
        self::assertSame(
            'unverifiable',
            $intake['rungs'][1]['state'],
            'MEDIA-MATURITY-UNVERIFIABLE-04: basamak "geçilmedi" değil "denetlenemedi" diye okunmalı.',
        );
        self::assertSame(
            'unverifiable',
            $intake['rungs'][1]['evidence'][0]['state'],
            'MEDIA-MATURITY-UNVERIFIABLE-04: kanıtın kendisi de denetlenemedi diye işaretlenmeli.',
        );
    }

    // --- MEDIA-MATURITY-SELF-ASSESSED-05 -----------------------------------

    /**
     * EKRAN KENDİNİ ÖVEMEZ: bu bir ÖZ DEĞERLENDİRMEDİR ve öyle yazar.
     *
     * Puan bir kalite belgesi değil, kanıt sayısının toplamıdır. Bunu
     * söylemeyen bir ekran, sahibin gözünde bağımsız bir denetim raporuna
     * dönüşür.
     */
    public function test_the_endpoint_declares_itself_a_self_assessment_with_a_bounded_score(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'olgunluk-oz');

        $response = $this->actingAs($owner)->getJson("/api/workspaces/{$workspaceId}/media/maturity");

        $response->assertOk();

        self::assertTrue(
            $response->json('selfAssessed'),
            'MEDIA-MATURITY-SELF-ASSESSED-05: uç, bunun bir öz değerlendirme olduğunu açıkça söylemeli.',
        );

        $achieved = (int) $response->json('score.achieved');
        $possible = (int) $response->json('score.possible');
        $capabilityCount = count((array) $response->json('capabilities'));

        self::assertSame(
            $capabilityCount * 4,
            $possible,
            'MEDIA-MATURITY-SELF-ASSESSED-05: azami puan yetenek sayısı × 4 olmalı; başka bir ölçek uydurulamaz.',
        );
        self::assertLessThanOrEqual($possible, $achieved, 'MEDIA-MATURITY-SELF-ASSESSED-05: puan tavanı aşamaz.');
        self::assertGreaterThan(0, $capabilityCount, 'MEDIA-MATURITY-SELF-ASSESSED-05: en az bir yetenek satırı olmalı.');
    }

    // --- MEDIA-MATURITY-TENANT-06 ------------------------------------------

    /**
     * Yabancı bu kiracının olgunluğunu HİÇ okuyamaz — 403 bile değil, 404.
     * "Yasak" cevabı, o kiracının VAR olduğunu söyler.
     */
    public function test_a_stranger_never_reads_another_tenants_maturity(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'olgunluk-kiraci');
        $stranger = $this->verifiedUser();

        $this->actingAs($stranger)
            ->getJson("/api/workspaces/{$workspaceId}/media/maturity")
            ->assertNotFound('MEDIA-MATURITY-TENANT-06: yabancıya 404 dönmeli.');
    }
}
