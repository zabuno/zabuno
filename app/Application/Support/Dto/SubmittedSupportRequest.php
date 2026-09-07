<?php

declare(strict_types=1);

namespace App\Application\Support\Dto;

use App\Domain\Support\DeliveryState;
use App\Domain\Support\SupportRequestStatus;

/**
 * Gönderim adımları bittikten sonra çağırana dönen hâl.
 *
 * `acknowledgement` alındı e-postasının GERÇEK hâlidir, bir tahmin değil:
 * kontrolcü "gönderildi" yazmadan önce buraya bakar. Sahibe giden
 * bildirimin hâli DÖNMEZ — o bizim iç meselemiz; ziyaretçiye "sahibe
 * ulaşamadık" demek ne bir çıkış yolu verir ne de onun sorunudur
 * (`docs/93`).
 */
final class SubmittedSupportRequest
{
    public function __construct(
        public readonly ReceivedSupportRequest $request,
        public readonly DeliveryState $acknowledgement,
    ) {}

    /**
     * Panel yanıtı: yalnız güvenli alanlar. Mesaj gövdesi geri
     * gönderilmez (ekran zaten yazdı), gönderim sebebi hiç çıkmaz.
     *
     * @return array{id:int,reference:string,subject:string,status:string,received_at:string,first_response_at:null,acknowledgement:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->request->id,
            'reference' => $this->request->reference,
            'subject' => $this->request->subject,
            'status' => SupportRequestStatus::Received->value,
            'received_at' => $this->request->receivedAt->format(DATE_ATOM),
            'first_response_at' => null,
            'acknowledgement' => $this->acknowledgement->value,
        ];
    }
}
