<?php

declare(strict_types=1);

namespace App\Application\Ordering\UseCase;

use App\Application\Ordering\Dto\OrderableLine;
use App\Application\Ordering\Dto\OrderDraft;
use App\Application\Ordering\Dto\OrderLineDraft;
use App\Application\Ordering\Exception\OrderLineRejectedException;

/**
 * SEPETİ SİPARİŞE ÇEVİREN TEK YER — `docs/115` §7 S1/S2 (FF-176).
 *
 * Misafirin gönderdiği şey yalnız "hangi satır, kaç adet"tir. Ad, fiyat,
 * para birimi ve alerjen BURADA, sunucunun bildiği gerçekten kopyalanır.
 * İstemcinin gönderdiği bir fiyatı kabul etmek, fiyatı misafire yazdırmak
 * olurdu; ve bunun fark edileceği tek an, ay sonu hesabın tutmadığı andır.
 *
 * SINIRLAR BURADA, EKRANDA DEĞİL. Ekran adet kutusunu 20'de durdurabilir;
 * istek yine elle gönderilir.
 *
 * Bu sınıf çerçeve BİLMEZ ve veritabanı GÖRMEZ: girdisi bir dizi, çıktısı
 * bir dizi. Böylece "yarınki fiyat dünkü siparişi değiştirmez" kuralı, bir
 * veritabanı ayağa kaldırmadan sınanabilir.
 */
final class BuildOrderLines
{
    /**
     * Tek siparişte kaç FARKLI ürün.
     *
     * Sayı bir masadan gelir, tavandan değil: bu ürünün modellediği en büyük
     * masa 20 kişiliktir (`StoreBulkQrCodesController::SEAT_COUNT_MAX`) ve
     * o masanın herkesi farklı bir ürün seçse bile 20 satır eder. 40, iki
     * turu birden sipariş eden bir masayı da taşır; ötesi bir masa değil,
     * gürültüdür.
     */
    public const MAX_DISTINCT_LINES = 40;

    /**
     * Tek satırda en fazla adet.
     *
     * Aynı gerekçe: 20, ürünün modellediği en büyük masada herkese birer
     * tane demektir. Daha fazlası masadan gelmiş olamaz.
     */
    public const MAX_QUANTITY_PER_LINE = 20;

    /**
     * @param  array<int, OrderableLine>  $orderable  menü satırı kimliği → sunucunun bildiği satır
     * @param  list<array{menuItemId:int, quantity:int}>  $requested
     *
     * @throws OrderLineRejectedException
     */
    public function handle(array $orderable, array $requested): OrderDraft
    {
        if ($requested === []) {
            throw OrderLineRejectedException::emptyOrder();
        }

        /*
            AYNI ÜRÜN İKİ KEZ GÖNDERİLİRSE BİRLEŞTİRİLİR, REDDEDİLMEZ.

            Sepet arayüzü aynı ürünü iki ayrı satır olarak biriktirebilir ve
            bu misafirin hatası değildir. Reddetmek, ona anlamadığı bir hata
            göstermek olurdu; birleştirmek ise mutfağa tek bir satır düşürür.
        */
        $quantities = [];

        foreach ($requested as $line) {
            $menuItemId = $line['menuItemId'];
            $quantity = $line['quantity'];

            if ($quantity < 1 || $quantity > self::MAX_QUANTITY_PER_LINE) {
                throw OrderLineRejectedException::invalidQuantity($menuItemId);
            }

            $quantities[$menuItemId] = ($quantities[$menuItemId] ?? 0) + $quantity;

            // Birleştirme sonrası da sınır geçerli: iki kez 15 göndermek,
            // 30 göndermenin dolambaçlı yolu olmamalı.
            if ($quantities[$menuItemId] > self::MAX_QUANTITY_PER_LINE) {
                throw OrderLineRejectedException::invalidQuantity($menuItemId);
            }
        }

        if (count($quantities) > self::MAX_DISTINCT_LINES) {
            throw OrderLineRejectedException::tooManyLines();
        }

        $lines = [];
        $total = 0;
        $currency = null;

        foreach ($quantities as $menuItemId => $quantity) {
            $source = $orderable[$menuItemId] ?? null;

            if ($source === null) {
                /*
                    Menüde olmayan, gizli ya da BAŞKA BİR KİRACININ satırı —
                    üçü de aynı cevabı alır. Ayrıştırmak, deneyerek başka bir
                    restoranın menü satırlarının var olduğunu ölçmeye izin
                    verirdi.
                */
                throw OrderLineRejectedException::itemUnavailable($menuItemId);
            }

            if ($source->isOutOfStock) {
                throw OrderLineRejectedException::outOfStock($menuItemId);
            }

            $currency ??= $source->currencyCode;

            if ($source->currencyCode !== $currency) {
                throw OrderLineRejectedException::currencyMismatch($menuItemId);
            }

            $lineTotal = $source->priceMinorAmount * $quantity;
            $total += $lineTotal;

            $lines[] = new OrderLineDraft(
                $menuItemId,
                $source->productName,
                $source->priceMinorAmount,
                $source->currencyCode,
                $quantity,
                $lineTotal,
                $source->allergens,
            );
        }

        return new OrderDraft($lines, $total, (string) $currency);
    }
}
