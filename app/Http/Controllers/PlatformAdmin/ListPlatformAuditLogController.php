<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Denetim günlüğü — `docs/122` §3 boşluk 6, dalga Y2.
 *
 * Ölçülen cümle şuydu: *"Kayıt yazılıyor, okunacak yer yok."* Dört tablo
 * aylardır doluyor — medya izi, menü izi, yayın geçmişi, kasa izi — ve
 * hiçbirinin platform düzeyinde okuyucusu yok. **Okunmayan denetim izi
 * yoktur:** yazılmış olması, sorulduğunda cevap verdiği anlamına gelmez.
 *
 * BİRLEŞTİRME UYGULAMADA, SQL'DE DEĞİL. Dört tablonun sütunları farklı; tek
 * bir `UNION` kurmak, beşinci kaynak eklendiği gün sorguyu büyütmek
 * demekti. Aynı sebeple her satır KENDİ kaynağını taşır: "silindi" kelimesi
 * bir fotoğrafta ve bir üründe aynı şeyi anlatmaz.
 *
 * SATIR YALNIZ "KİM, NE, NE ZAMAN" TAŞIR. Menü izinin öncesi/sonrası
 * değerleri (fiyat, alerjen) bu ekrana ÇIKMAZ: kiracının kendi menü izi
 * ekranında yerinde olan ayrıntı, kiracılar arası bir listede gereğinden
 * fazla veridir. Sır, jeton, oturum yükü ve IP hiçbir sorguya girmez.
 *
 * SAYFALAMA K-YOLLU BİRLEŞTİRMEDİR. Her kaynaktan yalnız istenen sayfaya
 * yetecek kadar satır çekilir, birleştirilir, sıralanır ve dilimlenir; bu,
 * en yeni N satırın doğru kümesini verir ve hiçbir kaynağı tamamen belleğe
 * almaz.
 *
 * FİLTRE SESSİZCE DÜŞMEZ. Bilinmeyen bir kaynak adı "hepsi" anlamına
 * gelmez, reddedilir: yok sayılan bir filtre, süperadmine gördüğünü
 * sandığından farklı bir liste gösterir.
 *
 * Bu uç SALT OKUNURDUR; hiçbir kaydı yazmaz, düzeltmez, silmez. Düzeltilebilen
 * bir denetim izi denetim izi değildir.
 */
final class ListPlatformAuditLogController extends Controller
{
    private const SOURCES = ['media', 'menu', 'publication', 'credential'];

    private const PER_PAGE_MAX = 200;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace' => ['sometimes', 'integer', 'min:1'],
            'source' => ['sometimes', 'string', 'in:'.implode(',', self::SOURCES)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:'.self::PER_PAGE_MAX],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['perPage'] ?? 50);
        $workspaceId = isset($validated['workspace']) ? (int) $validated['workspace'] : null;
        $source = $validated['source'] ?? null;

        $offset = ($page - 1) * $perPage;
        $need = $offset + $perPage + 1;

        $entries = [];

        foreach (self::SOURCES as $candidate) {
            if ($source !== null && $source !== $candidate) {
                continue;
            }

            /*
                Kasa izi hiçbir kiracıya ait DEĞİLDİR: platform anahtarı bir
                restoranın kaydı değil. Kiracıya göre süzülürken bu kaynak
                sessizce dışarıda kalır — "ait değil" ile "eşleşmedi" burada
                aynı sonucu verir ve satırı bir kiracıya yakıştırmak yanlış
                olurdu.
            */
            if ($candidate === 'credential' && $workspaceId !== null) {
                continue;
            }

            $entries = [...$entries, ...$this->readSource($candidate, $workspaceId, $need)];
        }

        /*
            Sıralama İKİ anahtarlı: zaman eşitse satır kimliği belirler.
            Tek anahtarla bırakılsaydı aynı saniyeye düşen iki olayın sırası
            istekten isteğe değişir ve sayfa 2, sayfa 1'in bir satırını
            tekrar gösterirdi.
        */
        usort($entries, static function (array $left, array $right): int {
            $byTime = strcmp((string) $right['at'], (string) $left['at']);

            return $byTime !== 0 ? $byTime : strcmp((string) $right['id'], (string) $left['id']);
        });

        $pageEntries = array_slice($entries, $offset, $perPage);

        return response()->json([
            'entries' => $pageEntries,
            'page' => $page,
            'perPage' => $perPage,
            'hasMore' => count($entries) > $offset + $perPage,
            'sources' => self::SOURCES,
        ]);
    }

    /**
     * @return list<array{id:string, source:string, action:string, subject:?string, actor:?string, workspaceId:?int, workspaceName:?string, at:?string}>
     */
    private function readSource(string $source, ?int $workspaceId, int $limit): array
    {
        return match ($source) {
            'media' => $this->mediaEntries($workspaceId, $limit),
            'menu' => $this->menuEntries($workspaceId, $limit),
            'publication' => $this->publicationEntries($workspaceId, $limit),
            default => $this->credentialEntries($limit),
        };
    }

    /** @return list<array<string, mixed>> */
    private function mediaEntries(?int $workspaceId, int $limit): array
    {
        $builder = DB::table('media_audits as a')
            ->join('workspaces as w', 'w.id', '=', 'a.workspace_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->leftJoin('media_assets as m', 'm.id', '=', 'a.media_asset_id')
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit($limit);

        if ($workspaceId !== null) {
            $builder->where('a.workspace_id', $workspaceId);
        }

        return $builder->get(['a.id', 'a.action', 'a.created_at', 'a.workspace_id', 'w.name as workspace_name', 'u.email', 'm.alt_text'])
            ->map(static fn (object $row): array => [
                'id' => 'media:'.$row->id,
                'source' => 'media',
                'action' => (string) $row->action,
                // Konu, kullanıcının YAZDIĞI alt metindir: "501 numaralı
                // varlık" hiçbir şey anlatmaz, "Kuzu pirzola" anlatır.
                'subject' => $row->alt_text === null ? null : (string) $row->alt_text,
                'actor' => $row->email === null ? null : (string) $row->email,
                'workspaceId' => (int) $row->workspace_id,
                'workspaceName' => (string) $row->workspace_name,
                'at' => $row->created_at === null ? null : (string) $row->created_at,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function menuEntries(?int $workspaceId, int $limit): array
    {
        $builder = DB::table('menu_audits as a')
            ->join('workspaces as w', 'w.id', '=', 'a.workspace_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit($limit);

        if ($workspaceId !== null) {
            $builder->where('a.workspace_id', $workspaceId);
        }

        /*
            `before_value`/`after_value` BİLEREK seçilmez. Kiracının kendi
            menü izi ekranında "380'den 420'ye" cümlesi sorunun cevabıdır;
            kiracılar arası platform günlüğünde aynı cümle, sorulmamış bir
            soruya verilmiş fazladan veridir.
        */
        return $builder->get(['a.id', 'a.action', 'a.subject_label', 'a.created_at', 'a.workspace_id', 'w.name as workspace_name', 'u.email'])
            ->map(static fn (object $row): array => [
                'id' => 'menu:'.$row->id,
                'source' => 'menu',
                'action' => (string) $row->action,
                'subject' => $row->subject_label === null ? null : (string) $row->subject_label,
                // Fail silinmişse alan boş kalır. Kaydı gizlemek yerine
                // failin bilinmediğini söylemek dürüst olandır.
                'actor' => $row->email === null ? null : (string) $row->email,
                'workspaceId' => (int) $row->workspace_id,
                'workspaceName' => (string) $row->workspace_name,
                'at' => $row->created_at === null ? null : (string) $row->created_at,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function publicationEntries(?int $workspaceId, int $limit): array
    {
        $builder = DB::table('menu_publications as p')
            ->join('workspaces as w', 'w.id', '=', 'p.workspace_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.published_by')
            ->leftJoin('locations as l', 'l.id', '=', 'p.location_id')
            ->orderByDesc('p.published_at')
            ->orderByDesc('p.id')
            ->limit($limit);

        if ($workspaceId !== null) {
            $builder->where('p.workspace_id', $workspaceId);
        }

        return $builder->get(['p.id', 'p.version', 'p.state', 'p.published_at', 'p.workspace_id', 'w.name as workspace_name', 'u.email', 'l.display_name'])
            ->map(static fn (object $row): array => [
                'id' => 'publication:'.$row->id,
                'source' => 'publication',
                'action' => (string) $row->state,
                // Hangi ŞUBE ve hangi SÜRÜM: "yayınlandı" tek başına, üç
                // şubeli bir işletmede hangi menünün değiştiğini söylemez.
                'subject' => trim(((string) ($row->display_name ?? '')).' · v'.(string) $row->version),
                'actor' => $row->email === null ? null : (string) $row->email,
                'workspaceId' => (int) $row->workspace_id,
                'workspaceName' => (string) $row->workspace_name,
                'at' => $row->published_at === null ? null : (string) $row->published_at,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function credentialEntries(int $limit): array
    {
        return DB::table('platform_credential_audits as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit($limit)
            ->get(['a.id', 'a.provider', 'a.action', 'a.created_at', 'u.email'])
            ->map(static fn (object $row): array => [
                'id' => 'credential:'.$row->id,
                'source' => 'credential',
                'action' => (string) $row->action,
                // Konu SAĞLAYICI ADIDIR; anahtarın kendisi, maskesi ve alan
                // içeriği bu tabloda zaten yoktur (`docs/94`).
                'subject' => (string) $row->provider,
                'actor' => $row->email === null ? null : (string) $row->email,
                // Platform anahtarı bir restoranın kaydı değildir; boş alan
                // burada dürüstlüktür.
                'workspaceId' => null,
                'workspaceName' => null,
                'at' => $row->created_at === null ? null : (string) $row->created_at,
            ])->all();
    }
}
