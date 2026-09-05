<?php

declare(strict_types=1);

namespace App\Application\Rating\UseCase;

use App\Application\Rating\Dto\RatingSignalDraft;
use App\Application\Rating\Port\RatingSignalRepositoryPort;
use App\Domain\Rating\RatingAbuse;
use App\Domain\Rating\RatingSource;
use App\Domain\Rating\RatingSubject;
use DateTimeImmutable;

/**
 * MASADAN GELEN OYUN DEFTERE GİRİŞİ — `docs/116` §4 (P4).
 *
 * ═══ BU SINIFIN TEK İŞİ: HANGİ SATIR SAYILIR ═══
 *
 * Kabul etme kararı denetleyicide (karekod, masa, ürün, ölçek), yazma işi
 * depoda. Burada yalnız kötüye kullanım kararı verilir ve o karar dosyadan
 * okunan kurallara dayanır — kodda hiçbir eşik yoktur.
 *
 * ═══ İKİ AYRI DURUM, İKİ AYRI SEBEP, TEK BİR CEVAP ═══
 *
 * 1. **Misafir fikrini değiştirdi.** Yeni satır sayılır, eskisi
 *    `superseded` olur. Değişmez bir defterde "fikrimi değiştirdim"in tek
 *    karşılığı yeni bir satırdır; eskisini güncellemek defterin
 *    değişmezliğini ilk gerçek kullanımda bozardı.
 *
 * 2. **Masadan ani yığılma geldi.** Yeni satır yazılır ama `burst_detected`
 *    ile işaretlenir.
 *
 * İkisinde de misafire AYNI cevap döner ve bu bilinçli:
 *
 * - **Reddetmek dürüst misafiri cezalandırır.** Sekiz kişilik bir masa on
 *   beş dakikada dokuz tabak puanlayabilir ve hiçbiri kötü niyetli
 *   değildir.
 * - **İşaret geri alınabilir, ret alınamaz.** Satır defterde durduğu sürece
 *   yanlış işaretleme bir gün düzeltilebilir; reddedilen bir oy geri gelmez.
 * - **Farklı cevap vermek eşiği ölçtürür.** "Bu oy sayılmadı" diyen bir
 *   yanıt, deneyerek tavanı bulmanın en ucuz yoludur.
 *
 * Misafire söylenen şey doğrudur: "fikrini kaydettik". Sayılıp sayılmadığı
 * misafirin değil, algoritmanın sorusudur ve cevabı defterde yazılıdır.
 *
 * ═══ ZİYARETÇİ ANAHTARININ SINIRI, YAZILI ═══
 *
 * `VisitorKey` tuzu HER GÜN DÖNER (`docs/68`): aynı telefon yarın başka bir
 * anahtar üretir ve aynı tabağa yeniden oy verebilir. Bu bir açık değil,
 * ödenmiş bir bedeldir — misafiri günler boyunca izlememenin bedeli. Gece
 * yarısından sağ çıkan koruma bu değil, YIĞILMA TESPİTİ ve HIZ SINIRIDIR;
 * ikisi de anahtara değil masaya ve isteğe bakar.
 */
final class RecordGuestRating
{
    public function __construct(private readonly RatingSignalRepositoryPort $signals) {}

    public function handle(
        int $workspaceId,
        RatingSubject $subjectType,
        int $subjectId,
        int $score,
        int $scaleMax,
        string $visitorKey,
        int $qrCodeId,
        int $diningTableId,
        RatingAbuse $abuse,
        DateTimeImmutable $now,
    ): void {
        $supersedes = $abuse->allowsOnlyOneSignalPerVisitorPerSubject()
            && $this->signals->hasCountedSignal($workspaceId, $subjectType->value, $subjectId, $visitorKey);

        // Sebep dizeleri dosyadaki KAPALI listeye karşı doğrulanır: kapalı
        // liste ancak doğrulanırsa kapalıdır.
        $supersedeReason = $supersedes
            ? $abuse->assertReasonIsDeclared(RatingAbuse::REASON_SUPERSEDED)
            : null;

        $this->signals->record(new RatingSignalDraft(
            $workspaceId,
            $subjectType,
            $subjectId,
            // Masadan gelen oy: kaynak sabittir çünkü bu uca başka bir
            // kaynaktan sinyal giremez. Dış kaynaklar kendi adaptörlerinden
            // gelecek (`docs/116` §5) ve çekirdek onların adını bilmeyecek.
            RatingSource::GuestScan,
            $score,
            $scaleMax,
            $visitorKey,
            $qrCodeId,
            $diningTableId,
            $now,
            $now,
            $this->exclusionFor($abuse, $workspaceId, $diningTableId, $supersedes, $now),
            $supersedeReason,
        ));
    }

    /**
     * Yığılma tespiti — işaret sebebi ya da `null`.
     *
     * FİKRİNİ DEĞİŞTİREN MİSAFİR YIĞILMA SAYILMAZ. Sayılsaydı, kararsız bir
     * misafir kendi masasını "kampanya" gibi gösterir ve masadaki herkesin
     * bundan sonraki oyunu ağırlıklandırma dışına attırırdı. Zaten
     * geçersizleşen bir satırın yerine yenisi geçiyor: masadaki SAYILAN oy
     * sayısı artmıyor.
     */
    private function exclusionFor(
        RatingAbuse $abuse,
        int $workspaceId,
        int $diningTableId,
        bool $supersedes,
        DateTimeImmutable $now,
    ): ?string {
        if ($supersedes) {
            return null;
        }

        $since = $now->modify('-'.$abuse->burstWindowMinutes().' minutes');

        $recent = $this->signals->countedSignalsFromTableSince($workspaceId, $diningTableId, $since);

        if ($recent < $abuse->burstMaxSignalsPerTable()) {
            return null;
        }

        return $abuse->assertReasonIsDeclared(RatingAbuse::REASON_BURST);
    }
}
