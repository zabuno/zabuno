<?php

declare(strict_types=1);

namespace App\Infrastructure\Workspace;

use App\Application\Workspace\Port\WorkspaceAuditTrailPort;
use Illuminate\Support\Facades\DB;

/**
 * İz, var olan kayıtlardan BİRLEŞTİRİLİR (FF-132).
 *
 * İki kaynak var ve ikisi de zaten yazılıyordu: medya izi ("bu fotoğrafı kim
 * sildi?") ve yayın geçmişi ("menü ne zaman yayına çıktı?"). Ayrı ayrı
 * durduklarında hiçbiri "çalışma alanında ne oldu" sorusunu cevaplamıyordu;
 * sahip iki farklı ekranda iki farklı zaman çizgisine bakıp kafasında
 * birleştirmek zorundaydı.
 *
 * Birleştirme UYGULAMADA yapılır, veritabanında bir UNION ile değil: iki
 * tablonun sütunları farklı ve bir gün üçüncü bir kaynak eklendiğinde
 * (fatura, takım) SQL'i büyütmek yerine bir dizi daha eklenir.
 *
 * ÜÇÜNCÜ KAYNAK EKLENDİ (`docs/122` Y7): platform ekibinin bu hesaba
 * bakışı. Yukarıdaki "yarın üçüncü bir kaynak eklendiğinde" cümlesi bir
 * varsayım değildi; bugün gerçekleşti ve tek satır maliyetle gerçekleşti.
 *
 * DÖRDÜNCÜ KAYNAK GELDİ (FF-226, `docs/138` §5): veri hakları defteri.
 * "Kim, ne zaman, hangi kapsamla dışa aktarma ya da silme istedi" sorusu
 * kiracının KENDİ görebileceği yerde durmak zorunda; platformun denetim
 * kaydında durması, kiracının kendi hakkını kendi ekranından
 * doğrulayamaması demekti. Yeni bir ekran açılmadı — sahip zaten
 * Ayarlar → Denetim izi ekranına bakıyor (`docs/133` deseni). Maliyet yine
 * tek satır oldu.
 *
 * Aktör E-POSTAYLA yazılır: bir ekipte iki "Mehmet" olabilir ve "Mehmet
 * sildi" cümlesi hiçbir soruyu kapatmaz. Kullanıcı silinmişse alan boş
 * kalır — kaydı gizlemek yerine failin bilinmediğini söylemek dürüst
 * olandır.
 */
final class EloquentWorkspaceAuditTrail implements WorkspaceAuditTrailPort
{
    public function recent(int $workspaceId, int $limit = 100): array
    {
        $events = [
            ...$this->mediaEvents($workspaceId, $limit),
            ...$this->publicationEvents($workspaceId, $limit),
            ...$this->supportAccessEvents($workspaceId, $limit),
            ...$this->dataRightsEvents($workspaceId, $limit),
        ];

        /*
            Sıralama İKİ anahtarlı: zaman eşitse kaynak adı belirler.
            Tek anahtarla bırakılsaydı aynı saniyede yazılmış iki olayın
            sırası çalıştırmadan çalıştırmaya değişir ve "sayfayı yenileyince
            sıra değişti" diye bir hata raporu doğardı.
        */
        usort($events, static function (array $left, array $right): int {
            $byTime = strcmp((string) $right['at'], (string) $left['at']);

            return $byTime !== 0 ? $byTime : strcmp($left['source'], $right['source']);
        });

        return array_slice($events, 0, $limit);
    }

    /**
     * ÜÇÜNCÜ KAYNAK: platform ekibinin bu hesaba bakışı (`docs/122` Y7,
     * `docs/133` §3).
     *
     * BU SATIRIN BURADA OLMASI PAKETİN ŞARTIDIR, süsü değil. `docs/122` §5
     * kaydın "kiracının GÖREBİLECEĞİ biçimde" yazılmasını istiyor; yalnız
     * süperadmin tarafında gösterilen bir kayıt denetim değil, bir günlük
     * dosyasıdır. Kayıt bu porta eklendiği an sahibin kendi Ayarlar →
     * Denetim izi ekranında, kendi menü ve medya olaylarının arasında,
     * onlarla aynı zaman çizgisinde belirir.
     *
     * KONU SEBEPTİR. Diğer kaynaklarda konu bir dosya ya da bir menü adıdır;
     * burada sahibin okuması gereken tek şey NEDEN bakıldığıdır. Sebebi
     * kısaltmak ya da kod adına çevirmek, kaydı yine okunmaz kılardı.
     *
     * @return array<int, array{source:string, action:string, subject:?string, actor:?string, at:?string}>
     */
    private function supportAccessEvents(int $workspaceId, int $limit): array
    {
        $rows = DB::table('support_access_sessions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.actor_user_id')
            ->where('s.workspace_id', $workspaceId)
            ->orderByDesc('s.started_at')
            ->orderByDesc('s.id')
            ->limit($limit)
            ->get(['s.reason', 's.started_at', 'u.email']);

        return $rows->map(static fn (object $row): array => [
            'source' => 'support-access',
            'action' => 'support_access_opened',
            'subject' => (string) $row->reason,
            'actor' => $row->email === null ? null : (string) $row->email,
            'at' => $row->started_at === null ? null : (string) $row->started_at,
        ])->all();
    }

    /**
     * @return array<int, array{source:string, action:string, subject:?string, actor:?string, at:?string}>
     */
    private function mediaEvents(int $workspaceId, int $limit): array
    {
        $rows = DB::table('media_audits as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->leftJoin('media_assets as m', 'm.id', '=', 'a.media_asset_id')
            ->where('a.workspace_id', $workspaceId)
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit($limit)
            ->get(['a.action', 'a.created_at', 'u.email', 'm.alt_text']);

        return $rows->map(static fn (object $row): array => [
            'source' => 'media',
            'action' => (string) $row->action,
            // Konu, kullanıcının YAZDIĞI alt metindir: "7 numaralı varlık"
            // hiçbir şey anlatmaz, "Kuzu pirzola" anlatır.
            'subject' => $row->alt_text === null ? null : (string) $row->alt_text,
            'actor' => $row->email === null ? null : (string) $row->email,
            'at' => $row->created_at === null ? null : (string) $row->created_at,
        ])->all();
    }

    /**
     * Veri hakları — FF-226 (`docs/138` §5).
     *
     * OLAY ADI DURUMLA BİRLEŞİR (`export.ready`, `erasure.scheduled`):
     * yalnız "export" yazsaydı, satır talebin istendiği mi yoksa
     * tamamlandığı mı anlaşılmazdı ve bir hukuk birimi için tam olarak o
     * ayrım önemlidir.
     *
     * KONU KAPSAMIN BÜYÜKLÜĞÜDÜR. Talebin kaç bölümü kapsadığı ve —
     * yürütülmüşse — kaç satırın gerçekten silindiği burada durur; sayı,
     * "tamamlandı" damgasının kontrol edilebilir olmasını sağlayan tek şey.
     *
     * @return array<int, array{source:string, action:string, subject:?string, actor:?string, at:?string}>
     */
    private function dataRightsEvents(int $workspaceId, int $limit): array
    {
        $rows = DB::table('workspace_data_requests as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.requested_by_user_id')
            ->where('d.workspace_id', $workspaceId)
            ->orderByDesc('d.requested_at')
            ->orderByDesc('d.id')
            ->limit($limit)
            ->get(['d.kind', 'd.state', 'd.scope', 'd.deleted_counts', 'd.requested_at', 'u.email']);

        return $rows->map(static function (object $row): array {
            $scope = json_decode((string) $row->scope, true);
            $sections = is_array($scope) ? count($scope) : 0;

            $counts = $row->deleted_counts === null ? null : json_decode((string) $row->deleted_counts, true);
            $deleted = is_array($counts) ? array_sum($counts) : null;

            return [
                'source' => 'data_rights',
                'action' => (string) $row->kind.'.'.(string) $row->state,
                /*
                    KONU BİR CÜMLE DEĞİL, BİR JETON. Bu listedeki `action`
                    sütunu zaten çevrilmemiş teknik jetonlar taşıyor
                    (`uploaded`, `published`); araya İngilizce bir cümle
                    koymak, çevrilemez borcu bir kaynak dizesi gibi
                    göstermek olurdu (`docs/121`).
                */
                'subject' => $deleted === null
                    ? 'sections='.$sections
                    : 'sections='.$sections.' · rows='.$deleted,
                'actor' => $row->email === null ? null : (string) $row->email,
                'at' => $row->requested_at === null ? null : (string) $row->requested_at,
            ];
        })->all();
    }

    /**
     * @return array<int, array{source:string, action:string, subject:?string, actor:?string, at:?string}>
     */
    private function publicationEvents(int $workspaceId, int $limit): array
    {
        $rows = DB::table('menu_publications as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.published_by')
            ->leftJoin('locations as l', 'l.id', '=', 'p.location_id')
            ->where('p.workspace_id', $workspaceId)
            ->orderByDesc('p.published_at')
            ->orderByDesc('p.id')
            ->limit($limit)
            ->get(['p.version', 'p.state', 'p.published_at', 'u.email', 'l.display_name']);

        return $rows->map(static fn (object $row): array => [
            'source' => 'publication',
            'action' => (string) $row->state,
            // Hangi ŞUBE ve hangi SÜRÜM: "yayınlandı" tek başına, üç şubeli
            // bir işletmede hangi menünün değiştiğini söylemez.
            'subject' => trim(((string) ($row->display_name ?? '')).' · v'.(string) $row->version),
            'actor' => $row->email === null ? null : (string) $row->email,
            'at' => $row->published_at === null ? null : (string) $row->published_at,
        ])->all();
    }
}
