<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destek talepleri — FF-201 (`docs/125`, `docs/107` Faz 1.6).
 *
 * `contact_messages` GENİŞLETİLMEDİ, yeni tablo açıldı. Eski tablo bir
 * "gelen kutusu"ydu: ad, e-posta, mesaj. Destek talebi ise bir YAŞAM
 * DÖNGÜSÜdür — referansı, durumu, ilk yanıt zamanı, hangi kanaldan ve hangi
 * çalışma alanından geldiği var. Aynı tabloya sütun eklemek, eski satırları
 * referanssız ve durumsuz bırakır; her sorguya "eski mi yeni mi" ayrımı
 * bindirirdi.
 *
 * Eski tablo SİLİNMEZ ve taşınmaz (geri döndürülebilirlik): kamu formu
 * artık oraya yazmaz, ama bugüne kadar düşen mesajlar yerinde durur.
 *
 * IP ve tarayıcı bilgisi yine SAKLANMAZ (`docs/68` ilkesi): "menüm
 * görünmüyor" diye yazan bir restoran sahibi, hakkında iz tutulmasını
 * gerektirmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table): void {
            $table->id();
            /*
                REFERANS OKUNUR VE BENZERSİZDİR: `ZB-` + beş karakter,
                karışan karakterler (0/O, 1/I/L) alfabede yok. Sütun 16
                karakter — biçim bir gün uzarsa yer var, ama bugünkü biçim
                sekiz karakterdir. Tekil indeks çarpışmayı veritabanında
                keser; depo kesildiğinde yeni numara dener.
            */
            $table->string('reference', 16)->unique();
            /*
                ÇALIŞMA ALANI VE KİŞİ İSTEĞE BAĞLIDIR: kamu formundan gelen
                talebin ikisi de yoktur. Çalışma alanı silinirse talep
                DURUR ve bağı boşa düşer — destek geçmişi kiracıyla birlikte
                yok olmamalı; "o restoran neden gitti?" sorusunun cevabı
                çoğu zaman son talebindedir.
            */
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('subject', 160);
            $table->text('message');
            // `public_contact` | `panel` — `App\Domain\Support\SupportChannel`.
            $table->string('channel', 20);
            // `received` | `answered` | `closed` — `App\Domain\Support\SupportRequestStatus`.
            $table->string('status', 20)->default('received');
            // Hangi dilde yazıldığı, cevabın hangi dilde yazılacağını söyler.
            $table->string('locale', 10)->nullable();
            $table->timestamp('received_at');
            // İlk yanıt BİR KEZ damgalanır: "kaç saatte cevap verdik"
            // ölçümünün kaynağı. Kapanıp yeniden açılsa bile değişmez.
            $table->timestamp('first_response_at')->nullable();
            /*
                İKİ AYRI GÖNDERİM, İKİ AYRI KAYIT.

                Alındı bildirimi GÖNDERENE gider (`acknowledged_*`), bildirim
                SAHİBE gider (`notified_*`). Eski tablonun tek damgası
                (`delivered_at`) yalnız sahibe gideni tutuyordu; şimdi ikisi
                de ayrı ayrı "çıktı mı, çıkmadıysa neden" cevabını taşır —
                biri düşüp öteki çıkabilir ve o durumda tek damga yalan
                söylerdi (`docs/93`, `docs/110` P0-06).
            */
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledgement_failure')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->string('notification_failure')->nullable();
            $table->timestamps();

            // Panel listesi: bu çalışma alanının talepleri, en yeni üstte.
            $table->index(['workspace_id', 'id']);
            // Süperadmin kuyruğu: duruma göre, en eski üstte.
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
