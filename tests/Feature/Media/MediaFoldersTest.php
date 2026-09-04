<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Medya klasörleri — `docs/108` §3 madde 1'in sunucu yarısı.
 *
 * Bugün elli fotoğraf tek bir düz listede duruyor. Sahibin gerçek sorusu
 * "adını hatırlamadığım o kampanya afişi nerede?" ve aramanın buna cevabı
 * yok: arama adı bilinen dosyayı bulur, klasör ise HATIRLAMADAN gezmeyi
 * mümkün kılar. Bu dosya o gezinmenin sunucu sözleşmesini dondurur.
 *
 * Kanonik kaynak `docs/reference/media-manager/Medya Yonetimi v2.dc.html`
 * ve `docs/108`; ikilemde onlar kazanır.
 *
 * Dondurulan üç davranış, hepsi kaynağın kendi verisinden okunmuştur:
 *
 * 1. **İki seviye, daha fazlası değil.** Kaynağın `folderDefs` listesinde
 *    yalnız `depth: 0` ve `depth: 1` var; kütüphane süzgeci de
 *    `f.folder === s.folder` ile YALNIZ doğrudan dosyaya bakıyor, alt
 *    klasörlerin dosyalarını üste toplamıyor. Sınırsız derinlik, kaynağın
 *    hiç göstermediği bir yetenek için özyinelemeli sayım, döngü kontrolü
 *    ve taşıma kuralları getirirdi.
 * 2. **Klasör silmek fotoğraf silmez.** Kaynağın değişmez kuralı "asıl
 *    korunur" (`docs/108` §4). Bir klasörü silen sahip bir RAFI kaldırdığını
 *    düşünür, üstündeki tabakları çöpe attığını değil; dosyalar klasörsüz
 *    kalır ve "Tümü"nde görünmeye devam eder.
 * 3. **Klasörsüz varlık kaybolmaz.** `media_folder_id` boş olan bugünkü
 *    elli fotoğraf, göç sonrası da listede durur — süzgeç sorulmadıkça
 *    hiçbir şey gizlenmez.
 *
 * Kiracı yalıtımı ve izin kapısı burada AYRI birer test olarak duruyor:
 * ikisi de "çalışıyor mu" sorusunun değil, "başkasının deposunu görebilir
 * mi" sorusunun cevabıdır ve bu soru sessizce bozulabilen tek sorudur.
 */
final class MediaFoldersTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->joinWorkspace($workspaceId, $owner, 'owner');

        return $workspaceId;
    }

    private function joinWorkspace(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Varlığı yükleme hattından geçirmeden doğrudan yazıyoruz: bu paket
     * klasörü sınıyor, karantina/tarama zincirini değil. Tarayıcı bu
     * ortamda `unavailable` (`docs/108` §5) ve gerçek yükleme hiçbir zaman
     * `ready` olmazdı — testin kırmızısı o zaman klasör hatasını değil
     * dağıtım engelini ölçerdi.
     */
    private function asset(int $workspaceId, string $name, ?int $folderId = null): int
    {
        return (int) DB::table('media_assets')->insertGetId([
            'workspace_id' => $workspaceId,
            'media_folder_id' => $folderId,
            'disk_path' => 'quarantine/'.$workspaceId.'/'.$name,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'alt_text' => $name,
            'slot' => 'menu',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function createFolder(User $actor, int $workspaceId, string $name, ?int $parentId = null): int
    {
        $response = $this->actingAs($actor)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/media/folders",
            ['name' => $name, 'parentId' => $parentId],
        );

        $response->assertStatus(201);

        return (int) $response->json('id');
    }

    // --- Listeleme: kaynağın sol kenar çubuğu -------------------------------

    public function test_folders_are_listed_as_a_two_level_tree_with_their_own_file_counts(): void
    {
        $owner = $this->verifiedUser();
        $workspace = $this->workspaceOwnedBy($owner, 'zeytin-klasor-liste');

        $products = $this->createFolder($owner, $workspace, 'Ürünler');
        $desserts = $this->createFolder($owner, $workspace, 'Tatlılar', $products);
        $campaigns = $this->createFolder($owner, $workspace, 'Kampanyalar');

        $this->asset($workspace, 'adana-kebap.jpg', $products);
        $this->asset($workspace, 'iskender.webp', $products);
        $this->asset($workspace, 'kunefe.avif', $desserts);
        // Klasörsüz: hiçbir klasörün sayacına girmez ama "Tümü"de durur.
        $this->asset($workspace, 'eski-fotograf.jpg');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspace}/media/folders"
        );

        $response->assertStatus(200);

        // Sıra kaynağın kenar çubuğundaki sıradır: kök klasör, hemen ardından
        // kendi çocukları. Girinti bu sıraya bakarak çizilir; sıra kararsız
        // olsaydı ekran her yenilemede zıplardı.
        self::assertSame(
            [
                ['id' => $products, 'parentId' => null, 'name' => 'Ürünler', 'fileCount' => 2],
                ['id' => $desserts, 'parentId' => $products, 'name' => 'Tatlılar', 'fileCount' => 1],
                ['id' => $campaigns, 'parentId' => null, 'name' => 'Kampanyalar', 'fileCount' => 0],
            ],
            array_map(
                static fn (array $folder): array => [
                    'id' => $folder['id'],
                    'parentId' => $folder['parentId'],
                    'name' => $folder['name'],
                    'fileCount' => $folder['fileCount'],
                ],
                $response->json('data'),
            ),
            'Klasör listesi kök→çocuk sırasıyla ve DOĞRUDAN dosya sayısıyla dönmeli.',
        );
    }

    // --- Oluşturma, adlandırma, derinlik sınırı ------------------------------

    public function test_a_folder_can_be_renamed_and_a_third_level_is_refused(): void
    {
        $owner = $this->verifiedUser();
        $workspace = $this->workspaceOwnedBy($owner, 'zeytin-klasor-derinlik');

        $products = $this->createFolder($owner, $workspace, 'Ürünler');
        $desserts = $this->createFolder($owner, $workspace, 'Tatlılar', $products);

        $renamed = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->patchJson(
            "/api/workspaces/{$workspace}/media/folders/{$desserts}",
            ['name' => 'Tatlı ve Pastalar'],
        );
        $renamed->assertStatus(200);
        $renamed->assertJsonPath('name', 'Tatlı ve Pastalar');

        // Üçüncü seviye kaynakta yok. Reddetmek, sessizce köke taşımaktan
        // dürüsttür: sahip klasörünü nereye koyduğunu bilerek çıkar.
        $tooDeep = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspace}/media/folders",
            ['name' => 'Baklavalar', 'parentId' => $desserts],
        );
        $tooDeep->assertStatus(422);

        // Aynı üst klasör altında aynı ad iki kez: sahip kendi klasörünü
        // ayırt edemez hâle gelirdi.
        $duplicate = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspace}/media/folders",
            ['name' => 'Ürünler', 'parentId' => null],
        );
        $duplicate->assertStatus(422);
    }

    // --- Silme: raf kalkar, tabaklar kalır ----------------------------------

    public function test_deleting_a_folder_releases_its_files_instead_of_destroying_them(): void
    {
        $owner = $this->verifiedUser();
        $workspace = $this->workspaceOwnedBy($owner, 'zeytin-klasor-silme');

        $campaigns = $this->createFolder($owner, $workspace, 'Kampanyalar');
        $poster = $this->asset($workspace, 'ramazan-afis.png', $campaigns);

        $deleted = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->deleteJson(
            "/api/workspaces/{$workspace}/media/folders/{$campaigns}"
        );
        $deleted->assertStatus(200);
        $deleted->assertJsonPath('releasedFileCount', 1);

        $library = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspace}/media"
        );
        $library->assertStatus(200);
        self::assertContains(
            $poster,
            array_column($library->json('data'), 'id'),
            'Klasör silindi diye fotoğraf kaybolamaz — "asıl korunur" (`docs/108` §4).',
        );
    }

    public function test_a_folder_that_still_holds_subfolders_refuses_to_be_deleted(): void
    {
        $owner = $this->verifiedUser();
        $workspace = $this->workspaceOwnedBy($owner, 'zeytin-klasor-dolu');

        $products = $this->createFolder($owner, $workspace, 'Ürünler');
        $this->createFolder($owner, $workspace, 'Tatlılar', $products);

        // Dosyayı serbest bırakmak geri alınabilir bir iştir (sahip dosyayı
        // "Tümü"de bulur); ALT KLASÖRÜ sessizce yok etmek değildir — orada
        // adını bildiği bir düzen kaybolur. Bu yüzden burada durup soruyoruz.
        $refused = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->deleteJson(
            "/api/workspaces/{$workspace}/media/folders/{$products}"
        );
        $refused->assertStatus(409);

        $stillThere = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspace}/media/folders"
        );
        self::assertContains($products, array_column($stillThere->json('data'), 'id'));
    }

    // --- Taşıma -------------------------------------------------------------

    public function test_an_asset_can_be_moved_into_a_folder_and_back_out_of_every_folder(): void
    {
        $owner = $this->verifiedUser();
        $workspace = $this->workspaceOwnedBy($owner, 'zeytin-klasor-tasima');

        $products = $this->createFolder($owner, $workspace, 'Ürünler');
        $asset = $this->asset($workspace, 'lahmacun.heic');

        $moved = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspace}/media/{$asset}/folder",
            ['folderId' => $products],
        );
        $moved->assertStatus(200);
        $moved->assertJsonPath('folderId', $products);

        // Geri çıkarmak da bir yetenek: yanlış klasöre atılan dosya kilitli
        // kalmamalı.
        $released = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspace}/media/{$asset}/folder",
            ['folderId' => null],
        );
        $released->assertStatus(200);
        $released->assertJsonPath('folderId', null);
    }

    // --- Süzme: var olan davranış bozulmadan ---------------------------------

    public function test_the_library_filters_by_folder_without_hiding_anything_when_unfiltered(): void
    {
        $owner = $this->verifiedUser();
        $workspace = $this->workspaceOwnedBy($owner, 'zeytin-klasor-suzme');

        $products = $this->createFolder($owner, $workspace, 'Ürünler');
        $inFolder = $this->asset($workspace, 'adana-kebap.jpg', $products);
        $unfiled = $this->asset($workspace, 'eski-fotograf.jpg');

        // Süzgeç SORULMADIKÇA hiçbir şey gizlenmez: bugünkü klasörsüz elli
        // fotoğraf göç sonrası da yerinde durur.
        $all = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspace}/media"
        );
        $all->assertStatus(200);
        self::assertEqualsCanonicalizing([$inFolder, $unfiled], array_column($all->json('data'), 'id'));

        $filtered = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspace}/media?folder={$products}"
        );
        $filtered->assertStatus(200);
        self::assertSame([$inFolder], array_column($filtered->json('data'), 'id'));

        // "Klasörsüzler" ayrı bir soru: sahip dağınık kalanı toplayabilmeli.
        $none = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspace}/media?folder=none"
        );
        $none->assertStatus(200);
        self::assertSame([$unfiled], array_column($none->json('data'), 'id'));
    }

    // --- Kiracı yalıtımı ----------------------------------------------------

    public function test_folder_endpoints_never_cross_the_workspace_boundary(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        $workspaceA = $this->workspaceOwnedBy($ownerA, 'zeytin-klasor-izolasyon-a');
        $workspaceB = $this->workspaceOwnedBy($ownerB, 'zeytin-klasor-izolasyon-b');

        $folderB = $this->createFolder($ownerB, $workspaceB, 'B Ürünleri');
        $assetB = $this->asset($workspaceB, 'b-logo.svg', $folderB);
        $assetA = $this->asset($workspaceA, 'a-logo.svg');

        $listA = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceA}/media/folders"
        );
        $listA->assertStatus(200);
        self::assertNotContains($folderB, array_column($listA->json('data'), 'id'));

        // Yabancı workspace'i adıyla çağırmak 404 döner, 403 değil: 403
        // "böyle bir depo var" derdi ve bu da bir bilgidir.
        $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspaceB}/media/folders")->assertStatus(404);

        // Kendi workspace'inin altından BAŞKASININ klasör kimliğini vermek,
        // kiracı sızıntısının en sessiz yoludur.
        $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())
            ->patchJson("/api/workspaces/{$workspaceA}/media/folders/{$folderB}", ['name' => 'Çalıntı'])
            ->assertStatus(404);

        $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())
            ->deleteJson("/api/workspaces/{$workspaceA}/media/folders/{$folderB}")
            ->assertStatus(404);

        $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$workspaceA}/media/{$assetA}/folder", ['folderId' => $folderB])
            ->assertStatus(404);

        $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$workspaceA}/media/{$assetB}/folder", ['folderId' => null])
            ->assertStatus(404);

        self::assertSame($folderB, (int) DB::table('media_assets')->where('id', $assetB)->value('media_folder_id'));
        self::assertNull(DB::table('media_assets')->where('id', $assetA)->value('media_folder_id'));
    }

    // --- İzin kapısı --------------------------------------------------------

    public function test_a_member_may_browse_folders_but_may_not_reshape_them(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();
        $outsider = $this->verifiedUser();
        $workspace = $this->workspaceOwnedBy($owner, 'zeytin-klasor-izin');
        // `member` rolü `WorkspaceView` taşır ama `MediaManage` taşımaz
        // (`RolePermissions`): salt okunur eski kayıt rolü.
        $this->joinWorkspace($workspace, $member, 'member');

        $folder = $this->createFolder($owner, $workspace, 'Belgeler');
        $asset = $this->asset($workspace, 'alerjen-tablosu.pdf');

        // Gezinmek görme iznidir: kütüphane listesi de aynı kapıdan geçiyor.
        $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspace}/media/folders")->assertStatus(200);

        // Düzeni DEĞİŞTİRMEK medya yönetme iznidir; burada 403 doğrudur,
        // çünkü üye deponun var olduğunu zaten biliyor.
        $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->postJson("/api/workspaces/{$workspace}/media/folders", ['name' => 'Gizli'])
            ->assertStatus(403);

        $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->patchJson("/api/workspaces/{$workspace}/media/folders/{$folder}", ['name' => 'Gizli'])
            ->assertStatus(403);

        $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->deleteJson("/api/workspaces/{$workspace}/media/folders/{$folder}")
            ->assertStatus(403);

        $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->putJson("/api/workspaces/{$workspace}/media/{$asset}/folder", ['folderId' => $folder])
            ->assertStatus(403);

        // Hiç üye olmayan için depo YOKTUR.
        $this->actingAs($outsider)->withHeaders($this->jsonHeaders())
            ->getJson("/api/workspaces/{$workspace}/media/folders")->assertStatus(404);
    }
}
