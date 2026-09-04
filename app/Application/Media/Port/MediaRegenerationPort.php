<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * BOYUT MOTORUNUN OKUMA YÜZEYİ — "kuralı değiştirdim, eskiler ne olacak?"
 *
 * Sahip "yeniden üretimi başlat" düğmesine basmadan ÖNCE kaç dosyanın
 * dokunulacağını bilmelidir. Uydurulmuş bir sayı kararı bilgisiz bırakır;
 * bu port yalnız gerçekten sayılmış satırı döner.
 */
interface MediaRegenerationPort
{
    /**
     * Yeniden üretimin dokunacağı dosyaların sayısı ve bugünkü türev sayısı.
     *
     * @return array{affectedAssets:int, existingRenditions:int}
     */
    public function stats(int $workspaceId): array;

    /**
     * GERÇEKTEN TARTILMIŞ baytlar — asıl dosya ve ondan üretilmiş EN BÜYÜK
     * türev.
     *
     * Kaynak "AVIF ~%74 küçük" gibi rakamlar gösteriyor; onlar biçimlerin
     * genel iddiasıdır, BU kiracının dosyalarının ölçümü değil. Burada
     * yalnız ölçülen döner ve hiç ölçüm yoksa üçü de sıfırdır — sıfır,
     * ekranın bölümü hiç çizmemesi için yeterli bir cevaptır.
     *
     * Karşılaştırma "asıl vs. en büyük türev"dir, "asıl vs. bütün türevlerin
     * toplamı" değil: misafirin tarayıcısı bir sayfada TEK bir türev
     * indirir, hepsini değil.
     *
     * @return array{assets:int, originalBytes:int, largestRenditionBytes:int}
     */
    public function measuredBytes(int $workspaceId): array;

    /**
     * Yeniden üretilebilecek hazır varlıkların kimlikleri.
     *
     * Sıra EN ESKİ ÜRETİLENDEN başlar (en küçük son-sürüm kimliği önce).
     * Kimlik sırasıyla dizmek, sınıra takılan sahip düğmeye yeniden
     * bastığında AYNI ilk yirmi beş dosyayı tekrar işlerdi ve kuyruğun
     * kalanına hiç sıra gelmezdi. Yeniden üretilen dosya yeni bir sürüm
     * kimliği aldığı için kendiliğinden listenin sonuna düşer.
     *
     * @return list<int>
     */
    public function readyAssetIds(int $workspaceId, int $limit): array;
}
