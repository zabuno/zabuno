<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * Bir AI YETENEĞİ — model değil.
 *
 * Ayrım kritik: "ürün açıklaması üret" bir yetenektir; onu hangi modelin
 * yaptığı bir yönlendirme kararıdır ve zamanla değişir. Kod yeteneği bilir,
 * modeli bilmez (`docs/51` §4.3).
 */
enum Capability: string
{
    // --- Stage 1 dikeyi: fotoğraf/PDF'ten menü çıkarma ------------------
    case OcrDocument = 'ocr.document';
    case MenuExtract = 'menu.extract';

    // --- Stage 1 çekirdeğinin sınadığı diğer yetenekler ----------------
    case TextEmbedding = 'embedding.text';
    case Classification = 'classification.text';

    /**
     * Bu yeteneğin çıktısı KULLANICI ONAYI olmadan uygulanabilir mi?
     *
     * Cevap her zaman hayırdır ve bu bilerek bir metottur, bir bayrak
     * değil: yeni bir yetenek eklendiğinde geliştirici burada durup
     * düşünmek zorunda kalsın. `docs/14` §4 bunu backend'de zorlar.
     */
    public function requiresHumanApproval(): bool
    {
        return true;
    }

    /**
     * Yeteneğin çıktı şeması. Şemaya uymayan cevap BAŞARISIZDIR ve
     * kullanıcıya ulaşmaz (`docs/51` UNK-02).
     */
    public function schemaVersion(): string
    {
        return match ($this) {
            self::OcrDocument => 'ocr-document.v1',
            self::MenuExtract => 'menu-extract.v1',
            self::TextEmbedding => 'embedding.v1',
            self::Classification => 'classification.v1',
        };
    }
}
