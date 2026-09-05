<?php

declare(strict_types=1);

namespace App\Infrastructure\MenuCatalog\Persistence;

use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menü denetim izini `menu_audits` tablosuna yazar — FF-154.
 *
 * İKİ KARAR BU SINIFTA YAŞIYOR.
 *
 * 1. **Yazma başarısız olursa asıl işlem BOZULMAZ, ama SESSİZ de kalmaz.**
 *    Sahip kebabın fiyatını 420'ye çıkardıysa fiyat 420'dir; denetim satırı
 *    yazılamadı diye bunu geri almak, yardımcı bir kaydı asıl işin şartına
 *    çevirirdi ve ürün, iz tablosu dolduğunda menü yönetilemez hâle
 *    gelirdi. Öte yandan hatayı yutmak da yanlış: izin sessizce çalışmayı
 *    bıraktığı bir ürün, izi hiç olmayan bir üründen daha tehlikelidir —
 *    çünkü kimse bunu fark etmez. `report()` hatayı uygulamanın kendi hata
 *    işleyicisine verir; ürün çalışmaya devam eder, arıza görünür kalır.
 *
 * 2. **INSERT kendi SAVEPOINT'inde çalışır.** PostgreSQL'de başarısız bir
 *    INSERT, içinde bulunduğu işlemin TAMAMINI zehirler (SQLSTATE 25P02):
 *    işlem kapanana kadar sonraki her sorgu reddedilir. Yani savepoint
 *    olmadan, "denetim yazımı asıl işlemi bozmasın" kuralı SQLite'ta
 *    çalışır, PostgreSQL'de çalışmazdı — istek bu kez denetim satırında
 *    değil, ondan SONRAKİ ilk sorguda düşerdi. İç içe `DB::transaction()`
 *    tam olarak bir savepoint kurar ve başarısızlığı yalnız kendi kapsamına
 *    geri sarar. Aynı gerekçe `EloquentMenuCatalogRepository`'de de yazılı.
 */
final class EloquentMenuAudit implements MenuAuditPort
{
    private const TABLE = 'menu_audits';

    /** `subject_label` sütununun sınırı; kaynak ad sütunlarıyla aynı. */
    private const LABEL_LIMIT = 255;

    public function record(MenuAuditEntry $entry): void
    {
        $row = [
            'workspace_id' => $entry->workspaceId,
            'menu_id' => $entry->menuId,
            'subject_type' => $entry->subject->value,
            'subject_id' => $entry->subjectId,
            'subject_label' => self::clip($entry->subjectLabel),
            'action' => $entry->action->value,
            'before_value' => $entry->before,
            'after_value' => $entry->after,
            'actor_user_id' => $entry->actorUserId,
            'created_at' => now(),
        ];

        try {
            DB::transaction(static function () use ($row): void {
                DB::table(self::TABLE)->insert($row);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Etiketi sütun sınırına kırpar.
     *
     * Kaynak ad sütunları da 255'tir, yani normal yolda kırpma HİÇ
     * çalışmaz. Yine de var: bileşik bir etiket ya da doğrulamadan kaçan
     * bir ad, PostgreSQL'de `value too long` (SQLSTATE 22001) ile satırı
     * düşürürdü. SQLite bunu sessizce kabul ettiği için hata yalnız CI'ın
     * PostgreSQL ayağında görünürdü. `mb_substr` karakter sayar; `varchar`
     * sınırı da karakterdir, bayt değil.
     */
    private static function clip(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        return mb_substr($label, 0, self::LABEL_LIMIT);
    }
}
