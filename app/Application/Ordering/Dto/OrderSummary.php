<?php

declare(strict_types=1);

namespace App\Application\Ordering\Dto;

use App\Domain\Ordering\OrderStatus;
use DateTimeImmutable;

/**
 * Panelin gördüğü sipariş — garson kuyruğu, mutfak monitörü ve geçmiş, ÜÇÜ DE.
 *
 * TEK TİP, çünkü üçü de aynı siparişi gösterir. Ekran başına ayrı bir tip
 * yazmak ilk bakışta daha temiz görünüyordu; ikinci bakışta aynı satırın üç
 * farklı hâli demekti — biri masayı, öbürü alerjeni, üçüncüsü ret sebebini
 * taşıyan. Ekranların hangi alanı ÇİZDİĞİ farklıdır; siparişin kendisi değil.
 *
 * ZAMAN MUTLAK ANDIR, saat değil. Ekran biçimlendirmeyi şubenin dilimiyle
 * yapar (`timeZone` yanında gelir), çünkü aynı çalışma alanında iki şehir
 * olabilir ve "18:41" hangi şehrin 18:41'i sorusu servis kaydını okunmaz
 * kılar.
 */
final class OrderSummary
{
    /** @param list<OrderLineSummary> $lines */
    public function __construct(
        public readonly int $id,
        public readonly OrderStatus $status,
        public readonly string $tableName,
        public readonly ?string $areaLabel,
        public readonly int $totalMinorAmount,
        public readonly string $currencyCode,
        public readonly ?string $rejectionReason,
        public readonly DateTimeImmutable $placedAt,
        public readonly DateTimeImmutable $statusChangedAt,
        public readonly ?string $timeZone,
        public readonly array $lines,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'tableName' => $this->tableName,
            'areaLabel' => $this->areaLabel,
            'totalMinorAmount' => $this->totalMinorAmount,
            'currencyCode' => $this->currencyCode,
            // Misafirin ekranında görünen cümlenin panel tarafındaki aslı
            // (`docs/115` G3). Geçmişte de durur: "neden reddedildi" sorusu
            // servis bittikten sonra sorulur.
            'rejectionReason' => $this->rejectionReason,
            'placedAt' => $this->placedAt->format(DateTimeImmutable::ATOM),
            'statusChangedAt' => $this->statusChangedAt->format(DateTimeImmutable::ATOM),
            'timeZone' => $this->timeZone,
            'lines' => array_map(
                static fn (OrderLineSummary $line): array => $line->toArray(),
                $this->lines,
            ),
        ];
    }
}
