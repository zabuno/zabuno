<?php

declare(strict_types=1);

namespace App\Domain\Content;

use InvalidArgumentException;

/**
 * BİR SAYFA hakkında bir insanın verdiği yayın kararı — `docs/144`.
 *
 * Yayın durumu bir bayrak değil, bir KARARDIR ve kararın üç parçası vardır:
 * hangi sayfa, kim verdi, neden. Üçünü ayrı ayrı taşımak yerine tek bir
 * nesnede tutmanın sebebi, eksik bir kararın hiç doğamamasıdır: sebebi
 * yazılmamış bir yayın, altı ay sonra kimsenin açıklayamadığı bir adrestir.
 *
 * Kararın NE ZAMAN UYGULANDIĞI burada YOK ve bilerek yok: o, kütükteki
 * `published_at` damgasının işidir. Aynı olgunun iki kaydı bir gün ayrışır.
 */
final class PublicationDecision
{
    public function __construct(
        public readonly string $pageKey,
        public readonly string $locale,
        /** Kararı veren kişi — bir rol adı değil, bir kişi. */
        public readonly string $decidedBy,
        /** `Y-m-d`. Serbest metin kabul edilmez: sıralanamayan tarih tarih değildir. */
        public readonly string $decidedOn,
        public readonly string $reason,
    ) {
        foreach ([
            'page_key' => $pageKey,
            'locale' => $locale,
            'decided_by' => $decidedBy,
            'reason' => $reason,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Yayın kararında boş alan: {$field}.");
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $decidedOn) !== 1) {
            throw new InvalidArgumentException(
                "Yayın kararının günü `Y-m-d` değil: {$decidedOn} ({$pageKey}).",
            );
        }
    }

    /**
     * Kararlar dosyasındaki satırları nesneye çevirir.
     *
     * Dosyanın kendisi bir yapılandırmadır ve yapılandırma yanlış yazılabilir;
     * bu yüzden çeviri SESSİZ DEĞİLDİR. Eksik bir alan burada patlar — yani
     * kod incelemesinde ya da testte, üretimde değil.
     *
     * @param  array<int, mixed>  $rows
     * @return list<self>
     */
    public static function listFrom(array $rows): array
    {
        $decisions = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException("Yayın kararı bir dizi değil (sıra {$index}).");
            }

            $decision = new self(
                pageKey: (string) ($row['page_key'] ?? ''),
                locale: (string) ($row['locale'] ?? ''),
                decidedBy: (string) ($row['decided_by'] ?? ''),
                decidedOn: (string) ($row['decided_on'] ?? ''),
                reason: (string) ($row['reason'] ?? ''),
            );

            $identity = $decision->locale.'|'.$decision->pageKey;

            /*
                AYNI SAYFA İKİ KEZ SAYILMAZ. İki satır iki farklı sebep
                taşısaydı, o sayfanın hangi sebeple açıldığı sorusunun iki
                cevabı olurdu — ve ikisi de doğru görünürdü.
            */
            if (isset($seen[$identity])) {
                throw new InvalidArgumentException("Aynı sayfa iki kez karara bağlanmış: {$identity}.");
            }

            $seen[$identity] = true;
            $decisions[] = $decision;
        }

        return $decisions;
    }
}
