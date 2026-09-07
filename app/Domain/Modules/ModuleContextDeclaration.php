<?php

declare(strict_types=1);

namespace App\Domain\Modules;

use InvalidArgumentException;

/**
 * `modules/<ad>.md` içindeki tek satırlık `contexts:` alanı (`docs/111` §4.2 B).
 *
 * İKİ İSİM UZAYI VARDI VE ARALARINDAKİ BAĞ HİÇBİR YERDE YAZILI DEĞİLDİ:
 * tanım dosyaları `menu-catalog`, `qr-print-export` diye adlandırılmış; kod
 * bağlamları `MenuCatalog`, `QrDestination`. `docs/111` §4.2 iki yol saydı ve
 * A'yı (eşlemeyi ekran koduna gömmek) açıkça reddetti: yanlış olduğunda
 * kimse fark etmez, hiçbir test kırılmaz. Seçilen B, eşlemeyi VERİ yapar ve
 * teste bağlar — bu sınıf o verinin dilbilgisidir.
 *
 * ÜÇ ŞEKİL VAR ÇÜNKÜ ÜÇ GERÇEK DURUM VAR:
 *
 *   contexts: MenuCatalog, Taxonomy
 *       Modül bu bağlamlara SAHİPTİR; ölçüm bu dizinler üzerinden yapılır.
 *
 *   contexts: yok
 *       Kod karşılığı yoktur. Bu bir ölçümdür, bir tahmin değil.
 *
 *   contexts: belirsiz: <sebep>
 *       Bağlam ayrıntısında ÖLÇÜLEMEZ. Tipik hâli, modülün başka bir
 *       bağlamın İÇİNDE bir dilim olmasıdır (örn. zamanlanmış yayınlama
 *       Publication'ın içinde yaşar ve kendi dizini yoktur). Böyle bir
 *       modülü ev sahibi bağlamla eşleştirmek, o bağlamın bütün kanıtını
 *       dilime mal etmek olurdu — ölçmeden "geçti" demenin tam kendisi.
 *       Sebep ZORUNLUDUR: gerekçesiz bir "bilinmiyor", sessizce "yok"
 *       demenin kibar biçimidir.
 */
final class ModuleContextDeclaration
{
    public const FIELD = 'contexts';

    private const NO_CODE_TOKEN = 'yok';

    private const UNKNOWN_TOKEN = 'belirsiz';

    /**
     * @param  list<string>  $contexts
     */
    private function __construct(
        private readonly string $kind,
        private readonly array $contexts,
        private readonly string $note,
    ) {}

    public static function parse(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException("`contexts:` alanı boş olamaz; `{$value}`.");
        }

        [$token, $rest] = array_pad(explode(':', $value, 2), 2, '');
        $token = strtolower(trim($token));
        $rest = trim($rest);

        if ($token === self::NO_CODE_TOKEN) {
            return new self('none', [], $rest);
        }

        if ($token === self::UNKNOWN_TOKEN) {
            if ($rest === '') {
                throw new InvalidArgumentException(
                    '`contexts: belirsiz` bir sebep taşımalı; gerekçesiz bir "bilinmiyor" sessizce "yok" demektir.'
                );
            }

            return new self('unknown', [], $rest);
        }

        $contexts = [];

        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);

            if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $candidate) !== 1) {
                throw new InvalidArgumentException(
                    "`contexts:` yalnız `app/` bağlam adı (PascalCase), `yok` ya da `belirsiz: <sebep>` taşıyabilir; bulunan: '{$candidate}'."
                );
            }

            $contexts[] = $candidate;
        }

        return new self('mapped', $contexts, '');
    }

    public function isMapped(): bool
    {
        return $this->kind === 'mapped';
    }

    public function isUnknown(): bool
    {
        return $this->kind === 'unknown';
    }

    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * @return list<string>
     */
    public function contexts(): array
    {
        return $this->contexts;
    }

    public function note(): string
    {
        return $this->note;
    }
}
