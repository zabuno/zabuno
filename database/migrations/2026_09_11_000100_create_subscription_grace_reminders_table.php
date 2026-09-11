<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ödemesiz süre hatırlatmasının damga defteri — `docs/107` Faz 1.3,
 * `docs/134`.
 *
 * ÖLÇÜLDÜ: sahip ödemesiz süreye girdiğini yalnız panele bakarsa
 * öğreniyordu. Hatırlatma günde bir koşan bir taramadır; defter olmasaydı
 * aynı sahip ödemesiz süre boyunca her gün aynı postayı alır ve posta
 * okunmaz olurdu.
 *
 * DAMGA ÜÇ ALANDAN OLUŞUR: çalışma alanı + DÖNEMİN bitişi + ALICI.
 *
 * - Dönemin bitişi anahtarın içindedir, çünkü YENİ BİR DÖNEM YENİ BİR
 *   OLAYDIR: sahip ödedi, dönem ilerledi ve yeniden gecikti — bu,
 *   susturulması gereken bir tekrar değil, ilk kez duyulan bir haberdir.
 *   Anahtar yalnız çalışma alanı olsaydı, ikinci gecikme sessiz geçerdi.
 * - Alıcı anahtarın içindedir, çünkü bir sahibin adresi düşerken diğerinin
 *   postası çıkmış olabilir; damga çalışma alanına basılsaydı, düşen alıcı
 *   bir daha HİÇ denenmezdi.
 *
 * DAMGA YALNIZ GERÇEK BİR DIŞARI GÖNDERİMDE BASILIR. `log` sürücüsü
 * gönderim değildir (bu deponun `MailDataRightsNotifier`/
 * `MailSupportNotifier` sözleşmesi): taşıyıcısı girilmemiş bir kurulumda
 * satır yazılsaydı, hatırlatma "gönderildi" sayılır ve taşıyıcı geldiğinde
 * bir daha hiç denenmezdi.
 *
 * `workspace_id` YABANCI ANAHTAR DEĞİLDİR: damga, çalışma alanı satırının
 * kendisinden bağımsız yaşar ve bir `cascade` yüzünden sessizce düşmez.
 * Ama bu bir SAKLAMA kararı değildir — kiracı verisinin silinmesi
 * istendiğinde bu satır da silinir (`TenantDataScope`, `docs/134` K15):
 * geriye kalacak tek şey bir e-posta adresi olurdu ve onu saklamak için
 * gösterebileceğimiz bir yükümlülük yok. Fatura/defter satırlarının
 * durmasının sebebi yasal belge olmalarıdır; bu satır ise yalnız tekrarı
 * önleyen işletme durumudur.
 *
 * ÖDEMESİZ SÜRENİN UZUNLUĞU BURADA YAZILI DEĞİL: o
 * `config/billing.php` içindedir ve evre `SubscriptionLifecycle`'da
 * hesaplanır. Defter yalnız "basıldı" damgasını tutar; bir sayıyı iki yere
 * yazmak, iki gün sonra ayrışmalarının garantisidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_grace_reminders', function (Blueprint $table): void {
            $table->id();

            // Yabancı anahtar DEĞİL: kayıt çalışma alanından uzun yaşar.
            $table->unsignedBigInteger('workspace_id');

            /*
                DÖNEMİN BİTİŞİ — `subscriptions.ends_at`'in o ANDAKİ değeri.
                Kopyadır ve kasıtlıdır: `ends_at` sahip ödeyince ileri
                taşınır, bu satır ise hangi döneme haber verdiğimizin
                değişmeyen kaydıdır.
            */
            $table->timestamp('period_ends_at');

            /*
                POSTANIN GERÇEKTEN ÇIKTIĞI ADRES — ÜRÜN SÖZLEŞMESİYLE AYNI
                GENİŞLİKTE. `users.email` Laravel'in varsayılan 255'idir ve
                kayıt doğrulaması da 255 karakterlik adrese izin verir.
                Burası 190 kalsaydı, kabul ettiğimiz ama defterin taşıyamadığı
                bir adres sınıfı doğardı: 191–255 karakterlik bir sahibin
                damgası ya kırpılarak yazılır ya hiç yazılamazdı. İkisinin de
                sonucu aynı: `alreadyNotified` bir daha ASLA eşleşmez ve o
                sahip ödemesiz süre boyunca her gün aynı postayı alır — yani
                defterin var olma sebebi tam da o sahipte çalışmaz.

                255 iki motorda da indekslenebilir kalır: bileşik anahtar en
                kötü hâlde ~1 KB'dir (8 + 4 + 4×255) ve hem InnoDB'nin
                DYNAMIC satır biçimindeki anahtar sınırının hem PostgreSQL
                B-tree'sinin satır sınırının belirgin biçimde altındadır.
                Genişliği büyütmek burada bir ödün değil, iki yerde duran
                aynı sözleşmeyi eşitlemektir.
            */
            $table->string('recipient_email', 255);

            $table->timestamp('sent_at');

            $table->timestamps();

            /*
                BENZERSİZLİK VERİTABANINDA — AMA NEYİ GARANTİ ETTİĞİ DAR,
                ve bunu olduğundan büyük yazmak defteri okuyanı yanıltır.

                İNDEKSİN GARANTİ ETTİĞİ: defterin tekliği. Aynı çalışma
                alanı + aynı dönem + aynı alıcı için ikinci bir damga satırı
                yazılamaz; `markNotified` çakışmayı `insertOrIgnore` ile
                sessizce yutar, çünkü aynı işin iki kez istenmesi bir arıza
                değildir.

                İNDEKSİN GARANTİ ETMEDİĞİ: postanın tekliği. Gerçek sıra
                `alreadyNotified` -> `notify` -> `markNotified`'dır ve damga,
                dondurulmuş ürün sözleşmesinin istediği gibi ancak dışarı
                gönderim BAŞARDIKTAN sonra basılır. İki koşu aynı anda
                okursa ikisi de "haber verilmemiş" görür ve posta iki kez
                çıkabilir; ikinci damga satırı düşer, ama çıkmış posta geri
                alınmaz. Damgayı denemeden ÖNCE basmak bu pencereyi
                kapatırdı — ve taşıyıcısı takılan bir kurulumda hatırlatmayı
                sessizce yakardı. Sözleşme bilerek ikincisini reddeder: bu
                tasarım "en çok bir kez" değil, "en az bir kez"dir.

                NORMAL İŞLETMEDE PENCERE AÇILMAZ: zamanlayıcı
                `routes/console.php`'de `withoutOverlapping` ile kuruludur ve
                paylaşımlı barındırmada uzayan bir tarama ertesi koşunun
                üstüne binmez. Geriye kalan tek hâl komutun ELLE ve eşzamanlı
                tetiklenmesidir; orada çift posta mümkündür ve bu, indeksin
                kapattığı bir şey değildir.

                İndeks adı elle verildi; üretilen ad MySQL'in 64 karakterlik
                tanımlayıcı sınırını aşıyordu.
            */
            $table->unique(
                ['workspace_id', 'period_ends_at', 'recipient_email'],
                'grace_reminder_once_per_period_and_recipient',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_grace_reminders');
    }
};
