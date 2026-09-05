<?php

declare(strict_types=1);

namespace App\Application\Team\Dto;

use App\Domain\Team\InvitationDeliveryState;

final class TeamInvitationSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $role,
        public readonly string $status,
        /*
            TESLİMAT DURUMU SATIRIN BİR PARÇASIDIR (`docs/110` P0-06).

            Bekleyen bir davetin iki apayrı sebebi olabilir: e-posta ulaştı
            ve kişi tıklamadı, ya da e-posta hiç çıkmadı. `status` ikisini de
            "pending" diye yazıyordu; sahip ekranda aynı satırı görüyor ve
            beklemekten başka bir şey yapamıyordu.

            Yalnız TÜRETİLMİŞ hâl dışarı çıkar — `delivered_at` zaman damgası
            ve `delivery_failure` metni asla. Sağlayıcının cevabı çoğu zaman
            uç adresini, alan adını ve yanıt gövdesini taşır; ekrana düşen
            böyle bir cümle, ürünün altyapısını tanıtan ücretsiz bir haritadır.
        */
        public readonly InvitationDeliveryState $delivery,
    ) {}

    /**
     * @return array{id:int,email:string,role:string,status:string,delivery:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'delivery' => $this->delivery->value,
        ];
    }
}
