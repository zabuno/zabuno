<?php

declare(strict_types=1);

use App\Domain\Rating\ExternalMatchConfidence;
use App\Domain\Rating\ExternalMatchedBy;
use App\Domain\Rating\ExternalReferenceDecision;
use App\Domain\Rating\ExternalSystem;
use App\Domain\Rating\RatingSubject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DIŞ KİMLİK EŞLEME TABLOSU — `docs/116` §1 Ö4 (P7). Ayrıntı: `docs/127`.
 *
 * ═══ NEDEN BU TABLO DIŞ KAYNAKTAN ÖNCE GELİYOR ═══
 *
 * Bu tablo olmadan dış veri ancak isim benzerliğiyle bağlanır — ve "Lezzet
 * Sarayı" adında üç restoran vardır. Yanlış eşleme, BAŞKASININ PUANINI
 * bizim restoranımızda göstermektir. Adaptörden sonra kurulsaydı, ilk çekim
 * turu isim benzerliğiyle yapılmış olurdu ve o satırların hangisinin doğru
 * eşleştiğini sonradan söyleyecek hiçbir kayıt kalmazdı.
 *
 * ═══ EŞLEME KESİN DEĞİLDİR: HER SATIR İDDİA VE CEVAP TAŞIR ═══
 *
 * `confidence` eşleştiricinin İDDİASIDIR, `owner_decision` sahibin
 * CEVABIDIR. İkisi ayrı sütundur çünkü ayrı şeylerdir: iddia tek başına
 * hiçbir kapıyı açmaz (§5 D4).
 *
 * ═══ NEDEN `owner_decision` BOŞ BIRAKILABİLİR ═══
 *
 * Boş = sahip henüz cevap vermedi. Üçüncü bir "pending" değeri
 * koymadık: aşağıdaki kısmî indeksler kapsamlarını zaten
 * `owner_decision = 'confirmed'` ile çiziyor ve "karar yok"un iki farklı
 * yazılışı (boş sütun ve `pending`) bir gün ayrışırdı.
 */
return new class extends Migration
{
    private const CONFIRMED_IDENTITY_INDEX = 'external_references_confirmed_identity_unique';

    private const CONFIRMED_SUBJECT_INDEX = 'external_references_confirmed_subject_unique';

    public function up(): void
    {
        Schema::create('external_references', function (Blueprint $table): void {
            $table->id();

            /*
                KİRACI KAPSAMI.

                `rating_signals`ın aksine burada SERT ANAHTAR var ve fark
                kasıtlı: o tablo değişmez bir ÖLÇÜM DEFTERİDİR ve işaret
                ettiği satırlardan daha uzun yaşamak zorundadır. Bu tablo
                bir defter değil, bir AYARDIR — kiracı yoksa ayarın da
                anlamı yoktur.
            */
            $table->foreignId('workspace_id')->constrained('workspaces');

            /*
                BİZİM VARLIĞIMIZ — şube ya da ürün (`docs/116` Ö4).

                Çok biçimli, dolayısıyla sert anahtar taşıyamaz. Silinmiş
                bir ürünün ardında kalan eşleme misafire hiçbir şey
                göstermez: okuma her zaman YAŞAYAN bir varlığın kimliğiyle
                başlar, tablodan varlık listesi türetilmez.
            */
            $table->string('subject_type', RatingSubject::MAX_VALUE_LENGTH);
            $table->unsignedBigInteger('subject_id');

            /*
                DIŞ SİSTEM VE ORADAKİ KİMLİK.

                191 karakter: bir Google yer kimliği yüz karakteri aşabilir.
                PostgreSQL `varchar(n)`'i UYGULAR, SQLite hiç uygulamaz —
                dar bir sütun yerelde sessizce kırpılmış gibi geçer,
                dağıtım motorunda isteği reddederdi.
            */
            $table->string('external_system', ExternalSystem::MAX_VALUE_LENGTH);
            $table->string('external_id', 191);

            /*
                DIŞ SİSTEMDE GÖRÜNEN İŞLETME ADI — sahibin karar verebilmesi
                için. Bu ad olmadan sahibe sorulan soru "ChIJ… kimliğini
                onaylıyor musunuz?" olurdu ve hiçbir sahip buna dürüstçe
                cevap veremezdi.

                §5 D5 ihlali değildir: yasak olan YORUM YAZARININ adı,
                fotoğrafı ve profilidir; bu alan bir işletme adı taşır.
            */
            $table->string('external_label', 191)->nullable();

            /* Eşleştiricinin kendi iddiası; tek başına hiçbir kapıyı açmaz. */
            $table->string('confidence', ExternalMatchConfidence::MAX_VALUE_LENGTH);

            /*
                KİM EŞLEDİ (otomatik mi, sahip mi) — KİM ONAYLADI'dan ayrı
                bir sorudur. Tek sütuna sıkıştırsaydık, sahibin onayladığı
                otomatik bir eşleme ile sahibin kendi yazdığı eşleme aynı
                görünürdü; yanlış eşleme incelenirken aradaki fark, hatanın
                nerede olduğunu söyleyen tek bilgidir.
            */
            $table->string('matched_by', ExternalMatchedBy::MAX_VALUE_LENGTH);
            $table->timestamp('matched_at');

            /*
                SAHİBİN CEVABI. Boş = henüz sorulmuş bir soru.

                Onaylayan kullanıcı silinse bile CEVAP KALIR
                (`nullOnDelete`): ekipten ayrılan bir yöneticinin hesabı
                kapandığı gün restoranın Google bağlantısı düşseydi, bir
                personel değişikliği misafirin gördüğü sayfayı sessizce
                değiştirirdi.
            */
            $table->string('owner_decision', ExternalReferenceDecision::MAX_VALUE_LENGTH)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            /*
                AYNI ÇİFT İKİ KEZ ÖNERİLEMEZ.

                Kısıt TÜM satırlar üzerindedir (kısmî değil) ve asıl işi
                reddedilmiş bir eşlemenin sessizce geri gelmesini
                engellemektir: sahip "bu benim restoranım değil" dedikten
                sonra eşleştirici yarın aynı çifti yeniden önerebilseydi,
                verilen cevap hiçbir şey ifade etmezdi.
            */
            $table->unique(
                ['workspace_id', 'subject_type', 'subject_id', 'external_system', 'external_id'],
                'external_references_pair_unique'
            );

            // Sahibin karar kutusu ve varlık başına okuma.
            $table->index(
                ['workspace_id', 'subject_type', 'subject_id'],
                'external_references_subject_index'
            );
            $table->index(['workspace_id', 'owner_decision'], 'external_references_decision_index');
        });

        /*
            ═══ TEK DOĞRU EŞLEME, VERİTABANI DÜZEYİNDE ═══

            İkisi de KISMÎ indekstir ve yalnız ONAYLI satırları kapsar.
            Koşulsuz olsalardı, bir eşleştiricinin üç aday önermesi
            imkânsızlaşırdı — oysa "hangisi doğru?" sorusunun sorulabilmesi
            için adayların yan yana durabilmesi gerekir.

            `WHERE` yan tümceli bir indeks hem PostgreSQL'de hem SQLite'ta
            AYNI sözdizimiyle çalışır (aynı desen `2026_09_08_000100`'de de
            kullanıldı). Desteklemeyen bir motora geçilirse bu göç
            GÜRÜLTÜYLE patlar, ki doğrusu budur: kısıt sessizce kaybolursa
            "tek doğru eşleme" bir cümleden ibaret kalır.

            Uygulama katmanı bu iki durumu ZATEN önceden kontrol eder
            (`DecideExternalReference`) — ama kontrol tek başına yeterli
            değildir: aynı anda gelen iki onay isteği "önce oku, sonra yaz"
            arasında birbirini görmez ve tek bir Google kaydı iki restorana
            birden bağlanırdı. Yarış koşulunu yorumla değil, kısıtla
            kapatıyoruz.
        */
        $confirmed = ExternalReferenceDecision::Confirmed->value;

        /*
            BİR DIŞ KİMLİK, TEK BİR VARLIK — VE BU KISIT KİRACIYI AŞAR.

            Kasıtlı olarak `workspace_id` taşımıyor. Bir Google yer kimliği
            TEK bir fiziksel yeri gösterir; iki farklı işletmenin ikisinin
            birden "burası benim" demesi mümkün değildir. Kiracıyla
            sınırlasaydık, komşu kiracının onayladığı kimliği biz de
            onaylayabilirdik — ve dış puan iki restoranda birden görünürdü.

            Bedeli açık ve kabul edilmiştir: bir sahip, başka bir kiracının
            önce davrandığı bir kimliği onaylayamaz. O durumda uygulama
            "bu kimlik başka bir kayda bağlı" der ve KİMİN bağladığını
            SÖYLEMEZ (`ExternalReferenceOutcome`).
        */
        DB::statement(
            'CREATE UNIQUE INDEX '.self::CONFIRMED_IDENTITY_INDEX.' ON external_references '
            ."(external_system, external_id) WHERE owner_decision = '".$confirmed."'"
        );

        /*
            BİR VARLIK, BİR DIŞ SİSTEMDE TEK KİMLİK.

            Bu kısıt kiracı içindedir, çünkü sorduğu soru bizim kendi
            varlığımız hakkındadır: bir şubenin iki farklı Google kaydına
            birden onaylı bağlanması, iki ayrı yerin puanını tek şubede
            toplamak olurdu.
        */
        DB::statement(
            'CREATE UNIQUE INDEX '.self::CONFIRMED_SUBJECT_INDEX.' ON external_references '
            ."(workspace_id, subject_type, subject_id, external_system) WHERE owner_decision = '".$confirmed."'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::CONFIRMED_IDENTITY_INDEX);
        DB::statement('DROP INDEX IF EXISTS '.self::CONFIRMED_SUBJECT_INDEX);
        Schema::dropIfExists('external_references');
    }
};
