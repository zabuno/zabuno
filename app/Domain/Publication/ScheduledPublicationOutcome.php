<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use Carbon\CarbonInterface;
use LogicException;

/**
 * Planın sahibe SÖYLENECEK hâli — kaydın teknik durumu değil, planın sözünü
 * tutup tutmadığı.
 *
 * NEDEN AYRI BİR KAVRAM: `ScheduledPublicationState` kaydın nerede olduğunu
 * söyler (`pending`, `publishing`, `failed`). Sahibin sorduğu soru başkadır:
 * "menüm değişti mi?" `pending` bunun cevabı değildir — vakti gelmemiş bir
 * plan da `pending`tir, zamanlayıcı öldüğü için saati geçmiş bir plan da.
 * İkisini aynı cümleyle göstermek, sahibe ikinci durumda yalan söylemekti.
 *
 * ÜÇÜ SAHİBİN MÜDAHALESİNİ İSTER (`Overdue`, `Interrupted`, `Failed`) ve
 * üçünde de aynı ürün gerçeği geçerlidir: MENÜ DEĞİŞMEDİ, misafir hâlâ
 * önceki sürümü görüyor. Bu iyi haberdir — bozuk bir yayın yayına girmedi —
 * ama sahip bunu bilmeden karar veremez.
 */
enum ScheduledPublicationOutcome: string
{
    /** Vakti gelmedi; her şey yolunda. */
    case Scheduled = 'scheduled';

    /** Şu anda yayına alınıyor. */
    case Publishing = 'publishing';

    /** Vakti geçti ve yayın ÇIKMADI. */
    case Overdue = 'overdue';

    /** Yayın başladı ama tamamlanmadı; kayıt asılı kaldı. */
    case Interrupted = 'interrupted';

    /** Yayın denendi ve kaydedilemedi. */
    case Failed = 'failed';

    /**
     * Zamanlayıcının normal gecikme payı.
     *
     * `routes/console.php` planı DAKİKADA BİR koşar ve `withoutOverlapping`
     * uzayan bir koşuda bir sonrakini atlatabilir. 09:00 planını 09:00:30'da
     * "çıkmadı" diye işaretlemek her plan için yalancı bir alarm üretirdi —
     * ve yalancı alarm, gerçek alarmı görünmez yapar. Pay TAHMİN DEĞİL:
     * dakikalık koşunun birkaç kez üst üste kaçırılmasına yeter, ondan
     * fazlasına yetmez.
     */
    public const GRACE_MINUTES = 5;

    /**
     * @param  CarbonInterface  $touchedAt  kaydın son dokunulma anı; `publishing`
     *                                      hâlinin ne kadardır asılı olduğunu yalnız bu söyler
     */
    public static function resolve(
        ScheduledPublicationState $state,
        CarbonInterface $scheduledFor,
        CarbonInterface $touchedAt,
        CarbonInterface $now,
    ): self {
        return match ($state) {
            ScheduledPublicationState::Pending => $now->greaterThan(
                $scheduledFor->clone()->addMinutes(self::GRACE_MINUTES)
            ) ? self::Overdue : self::Scheduled,

            ScheduledPublicationState::Publishing => $now->greaterThan(
                $touchedAt->clone()->addMinutes(self::GRACE_MINUTES)
            ) ? self::Interrupted : self::Publishing,

            ScheduledPublicationState::Failed => self::Failed,

            /*
                Buraya `published` ya da `cancelled` GELEMEZ: okuma sorgusu
                yalnız çözülmemiş kayıtları döndürür. Yine de sessizce bir
                etiket uydurmuyoruz — yanlış etiket, düzeltmeye çalıştığımız
                kusurun ta kendisidir.
            */
            default => throw new LogicException(
                "Çözülmüş bir plan sahibin ekranına gelemez: {$state->value}."
            ),
        };
    }

    /**
     * Menü değişmedi ve sahip bir şey yapmalı.
     */
    public function needsOwnerAttention(): bool
    {
        return match ($this) {
            self::Overdue, self::Interrupted, self::Failed => true,
            self::Scheduled, self::Publishing => false,
        };
    }
}
