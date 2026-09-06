<?php

declare(strict_types=1);

namespace App\Application\Workspace\Dto;

/**
 * Kurulumun beş adımı ve ilk yayına kadar geçen süre — tek bir okuma.
 *
 * Alanlar SAYIDIR, yorum değil: "menü bitti mi" sorusunun cevabı burada
 * `itemCount > 0` olarak türetilir (`docs/70` §2.1: içi boş menü bitmiş
 * sayılmaz) ve ekran aynı kuralı ikinci kez yazmaz.
 *
 * `firstPublishedAfterMinutes` YALNIZ bir yayın varsa doludur. Yayın yokken
 * `0` yazmak, "sıfır dakikada yayınladı" ile "hiç yayınlamadı"yı aynı
 * ortalamada toplardı (`docs/112` §3.4 ile aynı ilke).
 */
final readonly class SetupProgress
{
    public function __construct(
        public bool $brandDone,
        public bool $locationDone,
        public int $menuItemCount,
        public ?int $latestPublicationId,
        public ?int $latestPublicationVersion,
        public int $activeQrCount,
        public ?int $firstPublishedAfterMinutes,
    ) {}

    public function menuDone(): bool
    {
        return $this->menuItemCount > 0;
    }

    public function publicationDone(): bool
    {
        return $this->latestPublicationId !== null;
    }

    public function qrDone(): bool
    {
        return $this->activeQrCount > 0;
    }

    public function doneCount(): int
    {
        return count(array_filter([
            $this->brandDone,
            $this->locationDone,
            $this->menuDone(),
            $this->publicationDone(),
            $this->qrDone(),
        ]));
    }

    /**
     * Ekranın okuduğu gövde. Süre alanı yoksa anahtar da yoktur.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $publication = ['done' => $this->publicationDone()];

        if ($this->latestPublicationId !== null && $this->latestPublicationVersion !== null) {
            $publication['id'] = $this->latestPublicationId;
            $publication['version'] = $this->latestPublicationVersion;
        }

        $body = [
            'steps' => [
                'brand' => ['done' => $this->brandDone],
                'location' => ['done' => $this->locationDone],
                'menu' => ['done' => $this->menuDone(), 'itemCount' => $this->menuItemCount],
                'publication' => $publication,
                'qr' => ['done' => $this->qrDone(), 'activeCount' => $this->activeQrCount],
            ],
            'doneCount' => $this->doneCount(),
            'total' => 5,
        ];

        if ($this->firstPublishedAfterMinutes !== null) {
            $body['firstPublishedAfterMinutes'] = $this->firstPublishedAfterMinutes;
        }

        return $body;
    }
}
