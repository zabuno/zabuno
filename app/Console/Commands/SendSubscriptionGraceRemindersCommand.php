<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Billing\UseCase\SendDueSubscriptionGraceReminders;
use Illuminate\Console\Command;

/**
 * Ödemesiz süreye giren sahiplere hizmet e-postası — `docs/107` Faz 1.3,
 * `docs/134`.
 *
 * KOMUT BİR SAYI YAZMAZ. Ödemesiz sürenin uzunluğu `config/billing.php`
 * içindedir ve evre `SubscriptionLifecycle`'da hesaplanır; buraya bir
 * `--days` verilseydi, süre iki yerde yaşar ve iki gün sonra ayrışırlardı
 * (`zabuno:run-due-erasures` ile aynı ders).
 *
 * DÜŞEN BİR GÖNDERİM KOŞUYU BAŞARISIZ YAPAR — taramayı değil. Diğer
 * sahipler postasını alır; ama koşu sessizce BAŞARILI dönseydi, arıza
 * zamanlayıcının günlüğünde görünmez olurdu.
 *
 * TAŞIYICI YOKSA KOŞU BAŞARISIZ DEĞİLDİR. `log` sürücüsünde hiçbir şey
 * denenmemiştir ve denenmemiş bir iş düşmüş bir iş değildir: hatırlatma
 * borç kalır, taşıyıcı girildiği gün çıkar. Sayı yine de ekrana yazılır,
 * çünkü "hiçbir posta çıkmadı" bilgisi kurulumu yapan kişinin aradığı
 * bilgidir.
 */
final class SendSubscriptionGraceRemindersCommand extends Command
{
    protected $signature = 'zabuno:send-grace-reminders';

    protected $description = 'Ödemesiz süreye giren aboneliklerin bugünkü sahiplerine hizmet e-postası gönderir.';

    public function handle(SendDueSubscriptionGraceReminders $send): int
    {
        $result = $send->handle();

        $this->info(sprintf(
            'Gönderilen hatırlatma: %d · düşen: %d · taşıyıcı yok: %d',
            $result['sent'],
            $result['failed'],
            $result['undelivered'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
