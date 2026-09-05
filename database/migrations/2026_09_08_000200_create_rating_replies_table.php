<?php

declare(strict_types=1);

use App\Domain\Rating\RatingSubject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAHİBİN YANITI — `docs/116` §4 (P6).
 *
 * ═══ NEDEN AYRI BİR TABLO ═══
 *
 * Yanıt `rating_signals`'a bir sütun olarak eklenemez ve bu teknik bir
 * tercih değil: o tablo DEĞİŞMEZDİR. Sahibin cümlesi ise düzeltilebilir
 * olmak zorundadır — yanlış yazdığı bir cümleye sonsuza kadar mahkûm etmek
 * ürünün kimseye vermediği bir sözdür. İkisini aynı satıra koymak, ya
 * defterin değişmezliğini ya da sahibin düzeltme hakkını feda ederdi.
 *
 * ═══ SİLİNEBİLEN ŞEY SAHİBİN KENDİ SÖZÜDÜR, MİSAFİRİN ÖLÇÜMÜ DEĞİL ═══
 *
 * Bu tablodan bir satır silinebilir. `rating_signals`'tan silinemez. Ayrım
 * bu paketin tamamıdır: misafirin ölçümü sahibin malı değildir, sahibin
 * cümlesi sahibinindir. Silinebilen bir ortalama, misafire "bu restoranın
 * seçtiği oyların ortalaması" olarak gösterilirdi — yani bir ölçüm değil,
 * bir reklam.
 *
 * ═══ YAZAN GİDER, CÜMLE KALIR ═══
 *
 * `author_user_id` boş bırakılabilir ve kullanıcı silindiğinde boşalır
 * (`nullOnDelete`). Sert bir `cascade` olsaydı, ekipten ayrılan bir
 * yöneticinin hesabı kapandığı gün restoranın misafire söylediği cümle de
 * menüden düşerdi — yani bir personel değişikliği misafirin gördüğü sayfayı
 * sessizce değiştirirdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_replies', function (Blueprint $table): void {
            $table->id();

            // `rating_signals`/`rating_scores` ile aynı kiracı sınırı ve
            // aynı çok biçimli işaret; aynı sebeplerle sert anahtar taşımaz
            // (bkz. `2026_09_07_000100` göçü).
            $table->unsignedBigInteger('workspace_id');
            $table->string('subject_type', RatingSubject::MAX_VALUE_LENGTH);
            $table->unsignedBigInteger('subject_id');

            /*
                YANITIN METNİ.

                `text`, `varchar(n)` DEĞİL: uzunluk sınırı bir ÜRÜN kararıdır
                (`UpdateRatingReplyController::MAX_BODY_LENGTH`) ve orada
                dürüstçe reddedilir. Sütuna gömülseydi PostgreSQL taşan
                metni reddederken SQLite sessizce kabul ederdi — yani aynı
                cümle yerelde kaydedilir, dağıtımda 500 verirdi.
            */
            $table->text('body');

            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();

            /*
                YAYIN ANI, YAZIM ANINDAN AYRI SAKLANIR.

                Bugün ikisi aynı; ama sahip yarın bir yanıtı yazıp
                yayınlamadan bırakabilmek isterse, `published_at`'i boş
                bırakılabilir yapmak bir göç, o alanı sonradan icat etmek
                ise "eski satırlara ne yazacağız?" sorusudur.
            */
            $table->timestamp('published_at');

            $table->timestamps();

            /*
                BİR ÜRÜN İÇİN RESTORANIN TEK BİR SESİ VARDIR.

                İki yanıt iki ağız demekti: misafir hangisinin restoranın
                bugünkü sözü olduğunu bilemezdi. Düzeltmek yeni bir satır
                değil, aynı satırın üzerine yazmaktır — çünkü bu tablo
                bir ölçüm defteri değil, bir metin alanıdır.
            */
            $table->unique(['workspace_id', 'subject_type', 'subject_id'], 'rating_replies_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_replies');
    }
};
