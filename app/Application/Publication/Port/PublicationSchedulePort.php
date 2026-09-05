<?php

declare(strict_types=1);

namespace App\Application\Publication\Port;

use App\Application\Publication\Dto\ScheduledPublicationRecord;
use Carbon\CarbonInterface;

interface PublicationSchedulePort
{
    /**
     * Menü için bekleyen planı yazar.
     *
     * BİR MENÜNÜN AYNI ANDA TEK BEKLEYEN PLANI olur: sahip "bu gece 03:00"
     * dedikten sonra fikrini değiştirip "Pazartesi 09:00" derse, iki ayrı
     * yayın değil tek bir plan olmalıdır. Uygulama önceki bekleyen planı
     * iptal ederek yerine yenisini koyar.
     *
     * @param  array<string,mixed>  $snapshot
     * @param  list<int>  $visibleItemIds
     */
    public function schedule(
        int $workspaceId,
        int $menuId,
        int $locationId,
        CarbonInterface $scheduledFor,
        array $snapshot,
        array $visibleItemIds,
        ?int $brandId,
        int $scheduledByUserId,
    ): ScheduledPublicationRecord;

    /**
     * Sahibin HÂLÂ BİLMESİ GEREKEN plan — en yenisi.
     *
     * "Bekleyen" değil "ÇÖZÜLMEMİŞ": vakti gelmemiş bir plan da buraya
     * girer, zamanlayıcı çalışmadığı için vakti geçmiş bir plan da, yayının
     * ortasında asılı kalmış bir kayıt da, başarısız olmuş bir yayın da.
     * Yalnız `pending` okunsaydı, çıkmayan yayın sahibin ekranından
     * TAMAMEN kaybolurdu: plan yok, yayın yok, açıklama yok — menü de
     * değişmemiş. Sahip bunu ancak misafir şikâyet edince öğrenirdi.
     *
     * Sahibin kapattığı (`acknowledged_at`) kayıtlar dönmez.
     */
    public function unresolvedForMenu(int $workspaceId, int $menuId): ?ScheduledPublicationRecord;

    /**
     * Menünün bağlı olduğu ŞUBENİN saat dilimi (`locations.timezone`).
     *
     * SAAT DİLİMİ MARKANIN DEĞİL ŞUBENİN ALANIDIR (`docs/62`): aynı markanın
     * İstanbul, Dubai ve Berlin şubesi olabilir. Sabit bir dilim tek şubeli
     * bir işletmede doğru görünmeye devam eder ve ikinci şube açılır açılmaz
     * sessizce yanlış olur — Berlin şubesi "bu gece 03:00" dediğinde menü
     * hâlâ servis sürerken, 01:00'de misafirin elinde değişirdi.
     *
     * Zincir MENÜ → ŞUBE'dir ve çağırana çıkarttırılmaz: aynı birleştirmeyi
     * iki denetleyicide tekrar etmek, birinin bir gün kiracı koşulunu
     * unutmasına açık kapı bırakırdı.
     *
     * Cevap verilemiyorsa `null` döner: menü bu çalışma alanında yoktur,
     * şubenin saat dilimi boştur, ya da yazılı kimlik tanınmıyordur (elle
     * düzeltilmiş bir satır, emekliye ayrılmış bir dilim). Tanınmayan bir
     * kimliği ham hâlde geçirmek, ekranın o anı hiç yazamaması demekti.
     * YEDEK BİR DİLİME DE DÜŞÜLMEZ: sabit bir dilime
     * düşmek, düzeltilen hatanın kendisidir; bilinmeyen bir saat için
     * "bu gece 03:00" demek ise tutulamayacak bir sözdür.
     */
    public function timezoneForMenu(int $workspaceId, int $menuId): ?string;

    /** Bekleyen planı iptal eder; iptal edilecek plan yoksa `false`. */
    public function cancel(int $workspaceId, int $menuId, int $scheduleId): bool;

    /**
     * Çıkmamış bir yayının uyarısını sahibin ekranından düşürür.
     *
     * `state` DEĞİŞMEZ: başarısız bir plan `cancelled` yapılsaydı, "o gece
     * ne oldu — ben mi iptal ettim, yayın mı patladı" sorusunun cevabı
     * silinirdi. Kapatılacak bir kayıt yoksa `false`.
     */
    public function acknowledge(int $workspaceId, int $menuId, int $scheduleId): bool;

    /**
     * Vakti gelmiş bekleyen planları döndürür (en eski önce).
     *
     * @return list<ScheduledPublicationRecord>
     */
    public function due(CarbonInterface $now): array;

    /**
     * Kaydı `pending` → `publishing` yapar ve YALNIZ bu koşu sahiplendiyse
     * `true` döner. Aynı anda iki koşu varsa yalnız biri `true` alır.
     */
    public function claim(int $scheduleId): bool;

    public function markPublished(int $scheduleId, int $publicationId): void;

    public function markFailed(int $scheduleId): void;
}
