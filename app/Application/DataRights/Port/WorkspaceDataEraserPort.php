<?php

declare(strict_types=1);

namespace App\Application\DataRights\Port;

interface WorkspaceDataEraserPort
{
    /**
     * Kapsamdaki her satırı siler ve SİLİNENİ SAYAR.
     *
     * Sayı dönmesi bir kolaylık değil bir yükümlülük: "her şey silindi"
     * cümlesi ancak sayılabildiği kadar doğrudur. Sayı olmadan verilen bir
     * "tamamlandı" damgası, kontrol edilemeyen bir iddiadır.
     *
     * @return array<string, int> tablo adı → silinen satır sayısı
     */
    public function erase(int $workspaceId): array;

    /**
     * Yasal saklamaya alınmış (legal hold) medya sayısı.
     *
     * Sıfırdan büyükse silme HİÇ BAŞLAMAZ: bir uyuşmazlık kaydına bağlı
     * dosyayı silmek, kilidin var olma sebebini ortadan kaldırırdı
     * (`UpdateMediaLegalHoldController`).
     */
    public function assetsUnderLegalHold(int $workspaceId): int;
}
