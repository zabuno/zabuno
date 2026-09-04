<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Dto;

final class QrCodeRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $locationId,
        public readonly int $menuId,
        public readonly string $token,
        public readonly string $destinationType,
        public readonly string $state,
        /**
         * Kodun ait olduğu masanın adı ve alanının etiketi (FF-109).
         *
         * `qr_codes.dining_table_id` zaten yazılıyordu ama okuma tarafı onu
         * düşürüyordu: sahip 40 kod arasından "Masa 12"yi bulamıyor, ekranda
         * yalnız 43 karakterlik token'lar görüyordu. Yeniden bastırmak —
         * ürünün asıl işi — fiilen imkânsızdı.
         *
         * Masaya bağlı olmayan kod (giriş kodu) için `null`; uydurulmuş bir
         * ad, hiç ad olmamasından kötüdür.
         */
        public readonly ?string $tableName = null,
        public readonly ?string $areaLabel = null,
        /**
         * Alanın kimliği — toplu baskıyı SALONA GÖRE süzmek için (FF-122).
         *
         * Etiketle süzmek yeterli görünür ama değil: iki alan aynı adı
         * taşıyabilir ("Bahçe" iki katta da olabilir) ve o gün süzgeç sessizce
         * yanlış kartları basardı.
         */
        public readonly ?int $areaId = null,
    ) {}
}
