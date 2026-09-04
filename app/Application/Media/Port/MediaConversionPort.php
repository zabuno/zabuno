<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * DÖNÜŞTÜR bölümünün OKUMA yüzeyi (`docs/108` §6.3).
 *
 * İki soruyu cevaplar ve ikisinde de yalnız SAYILMIŞ/TARTILMIŞ olanı döner:
 *
 *   1. "Neyi dönüştürebilirim?" — kaynak listesi.
 *   2. "Daha önce dönüştürdüklerimde ne kazandım?" — biçim başına gerçek
 *      bayt. Kaynaktaki "%74" biçimin genel iddiasıdır; buradaki sayı bu
 *      kiracının kendi dosyalarının tartısıdır ve hiç ölçüm yoksa biçim
 *      listede HİÇ GÖRÜNMEZ. Sıfır bir kazanç değildir; ölçüm yokluğunu
 *      sıfırla göstermek "hiç kazanmadın" demek olurdu.
 */
interface MediaConversionPort
{
    /**
     * Dönüştürülebilir HAZIR görseller — adı ve gerçek boyutuyla.
     *
     * Yalnız `ready` varlıklar: bekleyen ya da reddedilmiş bir dosya
     * seçilemez, listeye koymak sahibi seçemeyeceği bir satırla
     * uğraştırırdı. SVG dışarıdadır — kaynak da onu dışarıda tutuyor
     * (`f.ext !== 'SVG'`): vektörü rasterleştirmek bir kazanç değil bir
     * kayıptır.
     *
     * @return list<array{id:int, name:string, sizeBytes:int, format:string}>
     */
    public function convertibleAssets(int $workspaceId, int $limit): array;

    /**
     * Biçim başına GERÇEKTEN tartılmış bayt.
     *
     * Karşılaştırma "asıl vs. o biçimin EN BÜYÜK türevi"dir, "asıl vs.
     * bütün türevlerin toplamı" değil: misafirin tarayıcısı bir sayfada
     * TEK bir türev indirir, hepsini değil (`MediaRegenerationPort::
     * measuredBytes` ile aynı gerekçe).
     *
     * @return array<string, array{assets:int, originalBytes:int, convertedBytes:int}>
     */
    public function measuredByFormat(int $workspaceId): array;
}
