<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * FF-156 — AI ONAYININ DA İZİ VAR.
 *
 * MÜŞTERİ SORUNU. FF-154 menü denetim izini kurdu ama bir delik bıraktı:
 * AI aktarımının onay yolu (`ai-imports/{artifact}/apply`) menüye YAZIYOR,
 * ize YAZMIYORDU. Bu, izin hiç olmamasından tehlikelidir; çünkü eksik bir
 * denetim izi TAM görünür. Sahip izi açar, aradığı fiyat değişikliğini
 * bulamaz ve "demek kimse dokunmamış" der — oysa fiyat, fotoğraftan okunan
 * bir taslağın onaylanmasıyla girmiştir.
 *
 * FAİL ONAYLAYAN İNSANDIR. "AI" diye bir kullanıcı uydurulmaz: menüye
 * yazma kararını veren, onay düğmesine basan kişidir (`docs/97` R4 —
 * "o önerir, siz onaylarsınız"). Ama aktarımın MAKİNE OKUMASINDAN geldiği
 * de kaybolmaz; onu ayrı bir olay taşır (`menu_ai_imported`), çünkü
 * sahibin bulduğu yanlış fiyatın sebebi tam olarak budur: sayıyı bir
 * fotoğraftan okuyan model yanlış okumuş olabilir. Elle yazılmış bir CSV
 * ile aynı güvenilirlik iddiası değildir.
 *
 * Gereksinim: MENU-AUDIT-AI-APPLY-09, MENU-AUDIT-AI-ACTOR-10,
 * MENU-AUDIT-AI-BULK-11, MENU-AUDIT-AI-NO-NOISE-12.
 */
final class AiApplyMenuAuditTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array{owner: User, workspace: int, location: int, menu: int} */
    private function restaurant(string $seed): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-brand',
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR', 'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul', 'address_line1' => 'Bahariye Cd. 1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        $menuId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü'],
        )->assertStatus(201)->json('id');

        return ['owner' => $owner, 'workspace' => $workspaceId, 'location' => $locationId, 'menu' => $menuId];
    }

    /**
     * Onaylanmayı BEKLEYEN bir makine okuması.
     *
     * Taslak doğrudan yazılır: bu paketin konusu sağlayıcı değil, ONAYIN
     * kaydıdır. Sağlayıcı yolunun kendi testleri var (`docs/92`).
     *
     * @param  list<array{category: string, product: string, price: int}>  $rows
     */
    private function pendingDraft(int $workspaceId, array $rows, string $capability = 'menu.extract'): int
    {
        $fields = [];

        foreach ($rows as $index => $row) {
            $fields[] = [
                'name' => 'row-'.($index + 1),
                'value' => [
                    'category' => $row['category'],
                    'product' => $row['product'],
                    'priceMinorAmount' => $row['price'],
                    'currencyCode' => 'TRY',
                ],
                'uncertain' => false,
                'confidence' => 0.97,
            ];
        }

        return (int) DB::table('ai_artifacts')->insertGetId([
            'workspace_id' => $workspaceId,
            'capability' => $capability,
            'model_identity' => 'fake/vision-1',
            'prompt_version' => 'menu.extract.v1',
            'schema_version' => 'menu.v1',
            'idempotency_key' => (string) Str::uuid(),
            'fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            'uncertain_field_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * İzin okunması gereken her şeyi tek bir dizide verir.
     *
     * @return list<array{action:string, subjectType:string, subjectId:int, subjectLabel:?string, before:?string, after:?string, actor:?int, menuId:?int}>
     */
    private function trail(int $workspaceId): array
    {
        return DB::table('menu_audits')
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [
                'action' => (string) $row->action,
                'subjectType' => (string) $row->subject_type,
                'subjectId' => (int) $row->subject_id,
                'subjectLabel' => $row->subject_label === null ? null : (string) $row->subject_label,
                'before' => $row->before_value === null ? null : (string) $row->before_value,
                'after' => $row->after_value === null ? null : (string) $row->after_value,
                'actor' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
                'menuId' => $row->menu_id === null ? null : (int) $row->menu_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{action:string, subjectType:string, subjectId:int, subjectLabel:?string, before:?string, after:?string, actor:?int, menuId:?int}>  $trail
     * @return list<array{action:string, subjectType:string, subjectId:int, subjectLabel:?string, before:?string, after:?string, actor:?int, menuId:?int}>
     */
    private function only(array $trail, string $action): array
    {
        return array_values(array_filter($trail, static fn (array $row): bool => $row['action'] === $action));
    }

    // --- MENU-AUDIT-AI-APPLY-09 / MENU-AUDIT-AI-ACTOR-10 --------------------

    /**
     * Onaylanan bir makine okuması ize TEK ÖZET SATIRI yazar ve satır
     * ONAYLAYAN kişiyi gösterir.
     *
     * Özet, CSV ile aynı gerekçeyle: bir KAYNAK BELGE (burada bir fotoğraf)
     * bir satırdır. Satır başına kayıt, 60 kalemlik bir menüde izi tek
     * başına doldurur ve tek tek yapılmış fiyat değişikliklerini görünmez
     * kılardı.
     */
    public function test_an_approved_ai_import_is_recorded_with_the_person_who_approved_it(): void
    {
        $r = $this->restaurant('ai-audit-apply');

        $artifactId = $this->pendingDraft($r['workspace'], [
            ['category' => 'Kebaplar', 'product' => 'Adana Kebap', 'price' => 38000],
            ['category' => 'Kebaplar', 'product' => 'Urfa Kebap', 'price' => 38000],
        ]);

        $this->actingAs($r['owner'])
            ->postJson("/api/workspaces/{$r['workspace']}/ai-imports/{$artifactId}/apply")
            ->assertOk();

        $imported = $this->only($this->trail($r['workspace']), 'menu_ai_imported');

        self::assertCount(1, $imported, 'MENU-AUDIT-AI-APPLY-09: AI onayı ize yazılmalı.');
        self::assertSame('menu', $imported[0]['subjectType']);
        self::assertSame($r['menu'], $imported[0]['subjectId']);
        self::assertSame('Ana Menü', $imported[0]['subjectLabel']);
        self::assertSame('1 kategori · 2 ürün', $imported[0]['after']);
        self::assertSame($r['menu'], $imported[0]['menuId']);
        self::assertSame(
            (int) $r['owner']->getKey(),
            $imported[0]['actor'],
            'MENU-AUDIT-AI-ACTOR-10: fail "AI" değil, onaya basan insandır.'
        );

        $at = DB::table('menu_audits')->where('action', 'menu_ai_imported')->value('created_at');
        self::assertNotNull($at, 'MENU-AUDIT-AI-APPLY-09: "ne zaman" kayıtta olmalı.');
    }

    /**
     * MAKİNE OKUMASI, ELLE YAZILMIŞ DOSYADAN AYRI OKUNUR.
     *
     * İkisi de "aktarım"dır ama aynı şey değildir: CSV'deki fiyatı sahip
     * yazdı, fotoğraftaki fiyatı bir model OKUDU. Sahip yanlış bir fiyat
     * bulduğunda ihtiyacı olan tam bu ayrımdır; onu özet metnine gömmek,
     * ileride izi okuyan her ekranı metin ayrıştırmaya zorlardı.
     */
    public function test_a_machine_reading_is_not_recorded_as_a_typed_file(): void
    {
        $r = $this->restaurant('ai-audit-distinct');

        $artifactId = $this->pendingDraft($r['workspace'], [
            ['category' => 'Çorbalar', 'product' => 'Mercimek Çorbası', 'price' => 9000],
        ]);

        $this->actingAs($r['owner'])
            ->postJson("/api/workspaces/{$r['workspace']}/ai-imports/{$artifactId}/apply")
            ->assertOk();

        $trail = $this->trail($r['workspace']);

        self::assertCount(1, $this->only($trail, 'menu_ai_imported'));
        self::assertSame([], $this->only($trail, 'menu_imported'), 'CSV olayı AI yolunda kullanılmamalı.');
    }

    // --- MENU-AUDIT-AI-BULK-11 ---------------------------------------------

    /**
     * TOPLU onay, ONAYLANAN TASLAK BAŞINA bir satır yazar.
     *
     * Aynı ölçüt: bir kaynak belge (bir fotoğraf sayfası) bir satır. Toplu
     * onay en çok 10 taslak alır (`ApplyBulkMenuAiImportController`), yani
     * satır sayısını menünün büyüklüğü değil, sahibin gözden geçirdiği
     * belge sayısı belirler. Tek bir birleşik satır yazsaydık, "hangi
     * fotoğraftan kaç ürün geldi" sorusu kaybolurdu.
     */
    public function test_a_bulk_approval_records_one_row_per_approved_draft(): void
    {
        $r = $this->restaurant('ai-audit-bulk');

        $first = $this->pendingDraft($r['workspace'], [
            ['category' => 'Kebaplar', 'product' => 'Adana Kebap', 'price' => 38000],
        ]);
        $second = $this->pendingDraft($r['workspace'], [
            ['category' => 'Tatlılar', 'product' => 'Baklava', 'price' => 16000],
            ['category' => 'Tatlılar', 'product' => 'Künefe', 'price' => 18000],
        ]);

        $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/ai-imports/batch/apply",
            ['artifactIds' => [$first, $second]],
        )->assertOk();

        $imported = $this->only($this->trail($r['workspace']), 'menu_ai_imported');

        self::assertCount(2, $imported, 'MENU-AUDIT-AI-BULK-11: her onaylanan taslak kendi satırını yazmalı.');
        self::assertSame('1 kategori · 1 ürün', $imported[0]['after']);
        self::assertSame('1 kategori · 2 ürün', $imported[1]['after']);
        self::assertSame((int) $r['owner']->getKey(), $imported[1]['actor']);
    }

    // --- MENU-AUDIT-AI-NO-NOISE-12 -----------------------------------------

    /**
     * İKİNCİ ONAY İZİ BÜYÜTMEZ.
     *
     * Ekran tazelenebilir, düğmeye ikinci kez basılabilir. İkinci istek
     * menüye hiçbir şey yazmaz (`applied_at`) — yazılmamış bir şey bir
     * değişiklik değildir ve ize girmez.
     */
    public function test_a_second_approval_of_the_same_draft_does_not_grow_the_trail(): void
    {
        $r = $this->restaurant('ai-audit-once');

        $artifactId = $this->pendingDraft($r['workspace'], [
            ['category' => 'Kebaplar', 'product' => 'Adana Kebap', 'price' => 38000],
        ]);

        $this->actingAs($r['owner'])
            ->postJson("/api/workspaces/{$r['workspace']}/ai-imports/{$artifactId}/apply")
            ->assertOk();
        $this->actingAs($r['owner'])
            ->postJson("/api/workspaces/{$r['workspace']}/ai-imports/{$artifactId}/apply")
            ->assertOk();

        self::assertCount(1, $this->only($this->trail($r['workspace']), 'menu_ai_imported'));
    }

    /**
     * HİÇBİR SATIRI OKUNAMAYAN bir taslak ize yazılmaz.
     *
     * "0 kategori · 0 ürün" satırı, menüde hiçbir şey değişmediği hâlde bir
     * değişiklik olmuş gibi okunurdu — CSV yolundaki "reddedilen satırlar
     * sayılmaz" kuralının aynısı.
     */
    public function test_an_approval_that_writes_nothing_is_not_recorded(): void
    {
        $r = $this->restaurant('ai-audit-empty');

        // Fiyatı okunamayan satır menüye YAZILMAZ (`ApplyMenuArtifact`).
        $artifactId = $this->pendingDraft($r['workspace'], [
            ['category' => 'Kebaplar', 'product' => 'Adana Kebap', 'price' => 0],
        ]);

        $this->actingAs($r['owner'])
            ->postJson("/api/workspaces/{$r['workspace']}/ai-imports/{$artifactId}/apply")
            ->assertOk();

        self::assertSame([], $this->only($this->trail($r['workspace']), 'menu_ai_imported'));
    }

    /**
     * AÇIKLAMA ÖNERİSİNİN ONAYI BİLEREK YAZILMAZ.
     *
     * FF-154 açıklama değişikliğini ize almamayı seçti ("açıklama pazarlama
     * metnidir") ve bunu elle düzenleme yolunda uyguladı
     * (`RenameMenuItemController`). AI yolunu ayrı tutmak, izi TERS yönde
     * yanıltırdı: sahip yalnız AI'ın dokunduğu açıklamaları görür, aynı
     * metni elle değiştiren editörü göremezdi. Kural tek: açıklama
     * kaydedilmez — kim değiştirirse değiştirsin.
     */
    public function test_approving_an_ai_description_is_deliberately_not_recorded(): void
    {
        $r = $this->restaurant('ai-audit-description');

        $categoryId = (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu/{$r['menu']}/categories",
            ['name' => 'Kebaplar'],
        )->assertStatus(201)->json('id');

        $itemId = (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}/menu-entries",
            ['productName' => 'Adana Kebap', 'price' => '380.00', 'currency' => 'TRY', 'allergens' => []],
        )->assertStatus(201)->json('id');

        $artifactId = (int) DB::table('ai_artifacts')->insertGetId([
            'workspace_id' => $r['workspace'],
            'capability' => 'product.description',
            'subject_id' => $itemId,
            'model_identity' => 'fake/text-1',
            'prompt_version' => 'product.description.v1',
            'schema_version' => 'description.v1',
            'idempotency_key' => (string) Str::uuid(),
            'fields' => json_encode(
                [['name' => 'description', 'value' => 'Odun ateşinde, acılı.']],
                JSON_THROW_ON_ERROR,
            ),
            'uncertain_field_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = $this->trail($r['workspace']);

        $this->actingAs($r['owner'])
            ->postJson("/api/workspaces/{$r['workspace']}/description-drafts/{$artifactId}/apply")
            ->assertOk();

        self::assertSame($before, $this->trail($r['workspace']), 'Açıklama onayı izi büyütmemeli.');
    }
}
