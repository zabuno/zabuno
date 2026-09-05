<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

/**
 * Ekrandaki bir MENÜ HAPI — kanonik kaynak `panel.dc.html`, "Menüler"
 * ekranı: "Ana menü yayında · Kahvaltı 07–11 · Ramazan kapalı".
 *
 * Hapın taşıdığı her alan gerçek veriden gelir; hiçbiri uydurulmaz
 * (`docs/109` §4 kural 3). `startsAt`/`endsAt` menünün kendi sütununda
 * DEĞİL, şubenin geçiş anlarından hesaplanır: bir menünün bitişi, ondan
 * sonraki menünün başlangıcıdır.
 */
final class MenuScheduleEntry
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        /** `draft` | `active` | `disabled` */
        public readonly string $state,
        public readonly int $sortOrder,
        /** "07:00" — rotasyonda değilse `null`. */
        public readonly ?string $startsAt,
        /** "11:00" — rotasyonda değilse `null`; `startsAt` ile eşitse TÜM GÜN. */
        public readonly ?string $endsAt,
        /**
         * Menünün BÜTÜN yayları. Bir menü günün birden çok parçasını
         * tutabilir: "Kahvaltı 07–11" dendiğinde ana menü 00:00–07:00 ve
         * 11:00–00:00 olmak üzere ikiye ayrılır. `startsAt`/`endsAt` bu
         * listenin ilkidir ve yalnız kısa gösterim içindir.
         *
         * @var list<array{startsAt:string,endsAt:string}>
         */
        public readonly array $windows,
        /** Şubenin saatiyle şu an servis edilen menü bu mu? */
        public readonly bool $isServingNow,
        /** Şubenin kalıcı genel adresi (ve karekod hedefi) bu menüde mi duruyor? */
        public readonly bool $isAddressAnchor,
    ) {}
}
