<?php

declare(strict_types=1);

namespace App\Application\Ai\UseCase;

use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\MenuCatalog\MenuAuditAction;
use Illuminate\Support\Facades\DB;

/**
 * İnsan onayını TASLAĞA yazar — `docs/92` (P0-05 foto yolu).
 *
 * YAYINA DOKUNMAZ. Onay, makinenin okuduğunu sahibin taslağına aktarır;
 * misafirin gördüğü menü sahip "Yayınla"ya basana kadar değişmez.
 *
 * İKİ KEZ UYGULANMAZ. Ekran tazelenebilir, düğmeye ikinci kez basılabilir,
 * istek tekrar gönderilebilir — menü iki katına çıkmamalı. `applied_at` bu
 * sorunun cevabıdır ve kilit veritabanındadır, ekranda değil.
 *
 * DENETİM İZİ BURADA, KONTROLCÜDE DEĞİL — FF-156. FF-154 kaydı
 * kontrolcülere koydu ve bu doğruydu: her kontrolcünün kendi olayı var.
 * Burada tersi geçerli — tekil onay ve TOPLU onay AYNI yazmayı yapan İKİ
 * kontrolcüdür ve kaydı ikisine ayrı ayrı koymak, birini güncelleyip
 * ötekini unutmanın kapısını açık bırakırdı. Yazmanın geçtiği tek yer
 * burasıdır; kaydın da tek yeri burası. (Aynı gerekçe medya tarafında da
 * uygulandı: `RunMediaBulkOperation` kendi izini kendi yazar.)
 */
final class ApplyMenuArtifact
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly MenuAuditPort $audit,
    ) {}

    /**
     * `$actorUserId` VARSAYILANSIZDIR ve bu bilinçli: bir varsayılan, yeni
     * bir çağıranın faili sessizce boş bırakmasına izin verirdi ve "kim
     * onayladı" sorusu tam da bu paketin cevapladığı sorudur. Yine de
     * `null` yazılabilir — insanın olmadığı bir yol bir gün doğarsa, iz
     * "faili bilmiyorum" demeli, faili uydurmamalı.
     *
     * @param  int|null  $actorUserId  onaya basan İNSAN; makine değil
     * @return array{importedItems: int, importedCategories: int, rejectedRows: list<array{row: string, reason: string}>, alreadyApplied: bool}
     */
    public function handle(int $workspaceId, int $menuId, int $artifactId, ?int $actorUserId): array
    {
        $artifact = DB::table('ai_artifacts')
            ->where('id', $artifactId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($artifact === null) {
            return ['importedItems' => 0, 'importedCategories' => 0, 'rejectedRows' => [], 'alreadyApplied' => false];
        }

        if ($artifact->applied_at !== null) {
            return ['importedItems' => 0, 'importedCategories' => 0, 'rejectedRows' => [], 'alreadyApplied' => true];
        }

        [$rows, $rejected] = $this->readRows($artifact);

        // Menünün adı yazmadan ÖNCE okunur: aktarım adı değiştirmez ama iz
        // olayın ANINDAKİ adı taşır ve bu okuma sırası FF-154'ün deseni.
        $menuName = $this->context->menuContext($menuId)?->name;

        $result = $rows === []
            ? ['categories' => 0, 'items' => 0]
            : $this->menuCatalog->importDraftRows($workspaceId, $menuId, $rows);

        /*
            DENETİM İZİ (FF-156) — ONAYLANAN TASLAK BAŞINA TEK ÖZET SATIRI.

            Fotoğraftan okunan bir taslağın onayı, menünün her fiyatını tek
            hamlede değiştirebilen bir yoldur; izsiz bırakıldığında "fiyatı
            kim değiştirdi" sorusu buradan sessizce kaçıyordu. Satır başına
            kayıt ise 60 kalemlik bir menüde izi tek başına doldurur — CSV
            yolundaki ölçünün aynısı: bir KAYNAK BELGE, bir satır.

            HİÇBİR ŞEY YAZILMADIYSA KAYIT DA YOK. "0 kategori · 0 ürün",
            menüde hiçbir şey değişmediği hâlde bir değişiklik olmuş gibi
            okunurdu; reddedilen satırlar zaten yanıtta raporlanıyor.
        */
        if ($result['categories'] > 0 || $result['items'] > 0) {
            $this->audit->record(MenuAuditEntry::forMenu(
                $workspaceId,
                $menuId,
                $menuName,
                MenuAuditAction::MenuAiImported,
                null,
                $result['categories'].' kategori · '.$result['items'].' ürün',
                $actorUserId,
            ));
        }

        DB::table('ai_artifacts')->where('id', $artifactId)->update([
            'reviewed_at' => now(),
            'applied_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'importedItems' => $result['items'],
            'importedCategories' => $result['categories'],
            'rejectedRows' => $rejected,
            'alreadyApplied' => false,
        ];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array{row: string, reason: string}>}
     */
    private function readRows(object $artifact): array
    {
        $fields = (array) json_decode((string) $artifact->fields, true);

        $rows = [];
        $rejected = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $value = $field['value'] ?? null;

            if (! is_array($value)) {
                continue;
            }

            $category = trim((string) ($value['category'] ?? ''));
            $product = trim((string) ($value['product'] ?? ''));
            $price = $value['priceMinorAmount'] ?? null;
            $currency = strtoupper(trim((string) ($value['currencyCode'] ?? '')));

            /*
                FİYATI OKUNAMAYAN SATIR YAZILMAZ.

                Uydurma bir fiyat yazmak, sahibin görmediği bir yanlışı
                menüye gömerdi; sıfır yazmak ise yayını kıran bir satır
                bırakırdı. Satır CSV yolundaki dille geri raporlanır ve sahip
                eksik olanı tamamlar — kaybolan bir şey yok.
            */
            if ($category === '' || $product === '') {
                $rejected[] = ['row' => $name, 'reason' => 'Kategori ya da ürün adı okunamadı.'];

                continue;
            }

            if (! is_int($price) || $price <= 0) {
                $rejected[] = ['row' => $name, 'reason' => 'Fiyat okunamadı; bu satırı elle ekleyin.'];

                continue;
            }

            if (strlen($currency) !== 3) {
                $rejected[] = ['row' => $name, 'reason' => 'Para birimi okunamadı.'];

                continue;
            }

            $rows[] = [
                'category' => $category,
                'product' => $product,
                'priceMinorAmount' => $price,
                'currencyCode' => $currency,
                'allergens' => [],
                'description' => null,
                // Görünür doğar (`docs/74`): sessiz bir aktivasyon duvarı
                // burada da kurulmamalı.
                'isVisible' => true,
            ];
        }

        return [$rows, $rejected];
    }
}
