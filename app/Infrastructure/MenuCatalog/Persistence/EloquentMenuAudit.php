<?php

declare(strict_types=1);

namespace App\Infrastructure\MenuCatalog\Persistence;

use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use Carbon\Carbon;
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
     * İzin bir sayfası — FF-163.
     *
     * ÜÇ KARAR BU METOTTA YAŞIYOR.
     *
     * 1. **Kiracı sınırı SORGUNUN İÇİNDE.** `where('a.workspace_id', ...)`
     *    hem sayımda hem satırlarda tekrar eder. Sınırı çağırana (ekrana ya
     *    da kontrolcüye) bırakmak, izi okuyan ikinci bir yüzey yazıldığı gün
     *    sessizce kaybolan bir güvenlik kuralı demekti.
     *
     * 2. **Fail E-POSTAYLA okunur, ADLA değil.** Bir ekipte iki "Mehmet"
     *    olabilir ve "Mehmet değiştirdi" cümlesi hiçbir soruyu kapatmaz.
     *    Kullanıcı silinmişse alan boş kalır: kaydı gizlemektense failin
     *    bilinmediğini söylemek dürüsttür. Aynı gerekçe medya izinde de
     *    yazılı (`EloquentMediaAudit`).
     *
     * 3. **Şubenin saat dilimi kaydın YANINDA döner, satırın içinde değil.**
     *    `menu_id` yabancı anahtar DEĞİL (menü silinince kayıt yaşamaya
     *    devam eder), bu yüzden bağ `leftJoin` ile kurulur ve menü yoksa
     *    dilim `null` olur. Sunucunun sabit bir dilime düşmesi tam olarak
     *    `docs/62`'de düzeltilen hatadır: Berlin şubesinin kaydını
     *    İstanbul saatiyle yazmak, ekrandaki saati gerçekte olan andan
     *    ayırırdı.
     *
     * @return array{total:int, rows: list<array{
     *     id:int, action:string, subjectType:string, subjectId:int,
     *     subjectLabel:?string, before:?string, after:?string,
     *     actor:?string, at:?string, timeZone:?string
     * }>}
     */
    public function recent(int $workspaceId, int $page, int $perPage): array
    {
        /*
            Sayım Laravel'in `count()`'una bırakılır; o, toplamayı
            `count(*) as aggregate` diye TAKMA ADLA yazar. Ham bir
            `count(*)` PostgreSQL'de okunamayan bir sütun adı üretir ve hata
            yalnız CI'ın PG ayağında görünürdü.
        */
        $total = DB::table(self::TABLE)->where('workspace_id', $workspaceId)->count();

        $rows = DB::table(self::TABLE.' as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->leftJoin('menus as m', 'm.id', '=', 'a.menu_id')
            ->leftJoin('locations as l', 'l.id', '=', 'm.location_id')
            ->where('a.workspace_id', $workspaceId)
            /*
                İKİ ANAHTARLI SIRA. Aynı saniyede yazılmış iki satırın sırası
                tek anahtarla çalıştırmadan çalıştırmaya değişirdi ve sahip
                "sayfayı yenileyince sıra değişti" derdi. Menüye tek bir
                istekte birden çok satır yazan yollar (ürün ekleme) tam
                olarak bu durumu üretir.
            */
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->forPage($page, $perPage)
            ->get([
                'a.id', 'a.action', 'a.subject_type', 'a.subject_id', 'a.subject_label',
                'a.before_value', 'a.after_value', 'a.created_at', 'u.email', 'l.timezone',
            ]);

        return [
            'total' => $total,
            'rows' => $rows->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'action' => (string) $row->action,
                'subjectType' => (string) $row->subject_type,
                'subjectId' => (int) $row->subject_id,
                'subjectLabel' => $row->subject_label === null ? null : (string) $row->subject_label,
                'before' => $row->before_value === null ? null : (string) $row->before_value,
                'after' => $row->after_value === null ? null : (string) $row->after_value,
                'actor' => $row->email === null ? null : (string) $row->email,
                'at' => self::instant($row->created_at),
                'timeZone' => $row->timezone === null ? null : (string) $row->timezone,
            ])->all(),
        ];
    }

    /**
     * Saklanan damgayı MUTLAK BİR ANA çevirir.
     *
     * Ham `created_at` metni ("2026-09-05 18:41:00") hangi dilimde olduğunu
     * SÖYLEMEZ; ekranda olduğu gibi yazıldığında okuyan onu kendi saati
     * sanar. Damga uygulamanın diliminde yazıldı (`config('app.timezone')`),
     * dışarıya ISO-8601 UTC olarak çıkar ve şubenin duvar saatine çevirmek
     * ekranın işidir.
     */
    private static function instant(mixed $createdAt): ?string
    {
        if ($createdAt === null) {
            return null;
        }

        try {
            return Carbon::parse((string) $createdAt, (string) config('app.timezone', 'UTC'))
                ->utc()
                ->toIso8601ZuluString();
        } catch (Throwable) {
            /*
                Okunamayan bir damga yüzünden İZİN TAMAMI kaybolmaz. Satır
                yine döner, yalnız "ne zaman"ı bilinmez — ekran bunu zaten
                söylüyor.
            */
            return null;
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
