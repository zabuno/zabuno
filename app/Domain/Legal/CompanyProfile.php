<?php

declare(strict_types=1);

namespace App\Domain\Legal;

use LogicException;

/**
 * Şirketin YASAL kimliği — ünvan, adres, MERSİS, vergi dairesi ve numarası,
 * e-posta, telefon (FF-198).
 *
 * VARSAYILAN YOK, UYDURMA YOK. Bütün alanlar `.env`'den gelir
 * (`config/legal.php`). Girilmemiş bir alan metinde "not yet provided"
 * olarak görünür; böylece belge yayınlanır ama söylemediği bir şeyi
 * söylemez. `config/contact.php` ve `config/legal.php#data_request` ile
 * aynı karar: sahte bir adres, sahibin cevap gelmeyen bir kutuya yazmasına
 * yol açardı — sahte bir tüzel kişi adı ise sözleşmenin tarafını yanlış
 * gösterirdi.
 *
 * "not yet provided" İNGİLİZCE ve bu dosyada: belge metniyle aynı kaynak
 * dilde, belge metninin parçası. Katalog anahtarı değil, çünkü belgenin
 * kendisi de katalogda değil (`LegalDocument`).
 */
final class CompanyProfile
{
    public const NOT_PROVIDED = 'not yet provided';

    public const FIELDS = ['legal_name', 'address', 'mersis', 'tax_office', 'tax_number', 'email', 'phone'];

    /** @param  array<string, string|null>  $fields */
    private function __construct(private readonly array $fields) {}

    public static function fromConfig(): self
    {
        /** @var array<string, mixed> $configured */
        $configured = (array) config('legal.company', []);

        return self::fromArray($configured);
    }

    /** @param  array<string, mixed>  $values */
    public static function fromArray(array $values): self
    {
        $fields = [];

        foreach (self::FIELDS as $name) {
            $value = $values[$name] ?? null;
            // Boş dize de "girilmedi"dir: yapılandırmada unutulmuş bir `=`
            // işareti, sözleşmede boş bir ünvan bırakırdı.
            $fields[$name] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return new self($fields);
    }

    public function field(string $name): ?string
    {
        return $this->fields[$name] ?? null;
    }

    /** @return list<string> */
    public function missing(): array
    {
        return array_values(array_keys(array_filter($this->fields, static fn (?string $value): bool => $value === null)));
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /**
     * `{company.<alan>}` yer tutucularını doldurur.
     *
     * Bilinmeyen bir alan bir YAZIM HATASIDIR ve sessizce geçilmez: metinde
     * `{company.adres}` kalması, okuyucuya bir şablon artığı göstermek
     * olurdu. Testler bütün belgeleri doldurduğu için hata CI'da görünür.
     */
    public function fill(string $text, string $locale = 'en'): string
    {
        return (string) preg_replace_callback(
            '/\{company\.([a-z_]+)\}/',
            function (array $match) use ($locale): string {
                if (! in_array($match[1], self::FIELDS, true)) {
                    throw new LogicException("Unknown company placeholder {company.{$match[1]}}.");
                }

                return $this->fields[$match[1]] ?? ($locale === 'tr' ? 'henüz belirtilmedi' : self::NOT_PROVIDED);
            },
            $text,
        );
    }
}
