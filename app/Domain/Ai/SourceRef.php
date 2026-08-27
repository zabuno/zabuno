<?php

declare(strict_types=1);

namespace App\Domain\Ai;

/**
 * Bir AI çıktısının NEREDEN geldiği.
 *
 * Model bir başvuru kaynağı DEĞİLDİR (`docs/51` §3.4). Kaynak, yüklenen
 * belgedir; model yalnız bir çıkarım motorudur. Bu yüzden üretilen her alan
 * hangi dosyanın hangi sayfasının hangi koordinatından geldiğini taşır.
 *
 * Bu olmadan "bu fiyat nereden geldi" sorusu cevapsız kalır — ve o soru
 * menü yayınlandıktan SONRA sorulur.
 */
final readonly class SourceRef
{
    /**
     * @param  array{x:float,y:float,w:float,h:float}|null  $boundingBox
     *                                                                    Kaynak sayfadaki konum, 0..1 aralığında oranlı. Piksel değil:
     *                                                                    aynı belge farklı çözünürlükte yeniden işlenebilir.
     */
    public function __construct(
        public string $fileHash,
        public ?int $page = null,
        public ?array $boundingBox = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'fileHash' => $this->fileHash,
            'page' => $this->page,
            'boundingBox' => $this->boundingBox,
        ];
    }
}
