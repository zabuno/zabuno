<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Port\SubscriptionGraceReminderNotifierPort;
use App\Application\Billing\Port\SubscriptionGraceReminderRepositoryPort;
use App\Domain\Billing\SubscriptionLifecycle;
use App\Domain\Billing\SubscriptionPhase;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;

/**
 * Ödemesiz süreye giren sahiplere hizmet e-postası — `docs/107` Faz 1.3'ün
 * tek açık maddesi, `docs/134`.
 *
 * ÖLÇÜLDÜ: sahip ödemesiz süreye girdiğini yalnız panele bakarsa
 * öğreniyordu. Ödenmiş dönem bitti, ödeme gelmedi, yetenekler bir süre daha
 * açık — ve bunu hiçbir yerden duymuyordu. Panele bakmayan sahip, ödemesiz
 * sürenin dolduğunu ancak bir şey kapandığında fark ederdi.
 *
 * ═══ EVRE TEK YERDEN OKUNUR ═══
 *
 * Bu paket İKİNCİ BİR EVRE HESABI AÇMAZ. `SubscriptionLifecycle` zaten
 * "hangi evredeyiz?" sorusunun tek cevabıdır ve yetenek okuması, abonelik
 * özeti ve panel aynı cevabı okur. Buraya "dönem bitti mi, kaç gün geçti"
 * diye ikinci bir hesap yazılsaydı, ödemesiz sürenin dönemden uzun
 * olamaması gibi bir sınır burada unutulur ve iki hesap sessizce ayrışırdı.
 *
 * `Active`, `Cancelling`, `Ended` ve `Suspended` SESSİZDİR. İptal etmiş
 * sahibe "ödemen gecikti" demek, kabul ettiğimiz iptali kabul etmemek
 * olurdu; askıdaki sahibe ödemesiz süreyi haber vermek ise geçmiş bir
 * tarihi bugünmüş gibi sunmaktır.
 *
 * ═══ PENCERE GENİŞ, KARAR DAR ═══
 *
 * Depodan okunan aralık, sınırlanmamış `grace_days` kadar geriye gider —
 * yani gerçek ödemesiz süreden (dönem kadarıyla sınırlı) asla DAR değildir.
 * Fazla okunan satırı evre eler; hiç okunmayan satır ise sessizce haber
 * alamazdı. Pencere bir tarama sınırıdır, bir evre kararı değil.
 *
 * ═══ BİR ALICININ DÜŞMESİ TARAMAYI KESMEZ ═══
 *
 * Sebep kaydedilir, diğer sahipler postasını alır ve düşen alıcı ödemesiz
 * süre sürdükçe ertesi günkü koşuda yeniden denenir — başarıya kadar,
 * başarıdan sonra bir daha değil (`RunDueErasures` ile aynı ders). Koşu
 * yine de BAŞARISIZ döner: sessizce başarılı dönmek, arızayı
 * zamanlayıcının günlüğünde görünmez kılardı.
 */
final readonly class SendDueSubscriptionGraceReminders
{
    public function __construct(
        private SubscriptionGraceReminderRepositoryPort $reminders,
        private SubscriptionGraceReminderNotifierPort $notifier,
        private ConfigRepository $config,
    ) {}

    /**
     * @return array{sent:int, failed:int, undelivered:int}
     */
    public function handle(): array
    {
        $now = Carbon::now();
        $graceDays = $this->graceDays();
        $periodDays = $this->periodDays();

        $sent = 0;
        $failed = 0;
        $undelivered = 0;

        $candidates = $this->reminders->candidates(
            $now->copy()->subDays($graceDays),
            $now,
        );

        foreach ($candidates as $candidate) {
            $lifecycle = SubscriptionLifecycle::of(
                $candidate['endsAt'],
                $candidate['cancelledAt'],
                $graceDays,
                $periodDays,
                $now,
            );

            if ($lifecycle->phase !== SubscriptionPhase::Grace || $lifecycle->graceEndsAt === null) {
                continue;
            }

            foreach ($this->reminders->currentOwnerEmails($candidate['workspaceId']) as $recipient) {
                if ($this->reminders->alreadyNotified($candidate['workspaceId'], $candidate['endsAt'], $recipient)) {
                    continue;
                }

                $outcome = $this->notifier->notify($recipient, $candidate['endsAt'], $lifecycle->graceEndsAt);

                if ($outcome === null) {
                    /*
                        DAMGA YALNIZ BURADA BASILIR — gerçek bir dışarı
                        gönderimden SONRA. Denemeden önce basılsaydı,
                        taşıyıcısı takılan bir kurulumda hatırlatma
                        "gönderildi" sayılır ve bir daha hiç denenmezdi.
                    */
                    $this->reminders->markNotified($candidate['workspaceId'], $candidate['endsAt'], $recipient);
                    $sent++;

                    continue;
                }

                if ($outcome === false) {
                    // Dışarı giden taşıyıcı yok: arıza değil, borç.
                    $undelivered++;

                    continue;
                }

                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'undelivered' => $undelivered];
    }

    private function graceDays(): int
    {
        return max((int) $this->config->get('billing.subscription.grace_days', 0), 0);
    }

    private function periodDays(): int
    {
        $days = (int) $this->config->get('billing.subscription.period_days', 30);

        return $days > 0 ? $days : 30;
    }
}
