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
 * Aktör E-POSTAYLA yazılır: bir ekipte iki "Mehmet" olabilir ve "Mehmet
 * sildi" cümlesi hiçbir soruyu kapatmaz. Kullanıcı silinmişse alan boş
 * kalır — kaydı gizlemek yerine failin bilinmediğini söylemek dürüst
 * olandır.
 */
final class EloquentWorkspaceAuditTrail implements WorkspaceAuditTrailPort
{
    public function recent(int $workspaceId, int $limit = 100): array
    {
        $events = [...$this->mediaEvents($workspaceId, $limit), ...$this->publicationEvents($workspaceId, $limit)];

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
