<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Application\Support\Dto\NewSupportRequest;
use App\Application\Support\Dto\SubmittedSupportRequest;
use App\Application\Support\Port\SupportNotifierPort;
use App\Application\Support\Port\SupportRequestRepositoryPort;
use App\Domain\Support\DeliveryState;

/**
 * Talebi ALIR — FF-201 (`docs/125` §1).
 *
 * Sıra sabittir ve kamu formuyla panel aynı sırayı paylaşır:
 *
 *   1. SAKLA ve referans ver. Bundan sonrası düşse bile talep durur.
 *   2. GÖNDERENE alındı bildirimi; sonucu satıra yaz.
 *   3. SAHİBE bildirim (adres varsa); sonucu satıra yaz.
 *
 * Saklamak göndermekten önce gelir (`docs/93`): sağlayıcı bir gün cevap
 * vermediğinde kaybolan bir talep olmamalı. İki gönderim birbirinden
 * BAĞIMSIZDIR — biri düşünce öteki denenir; müşteriye alındı gitmedi diye
 * sahibi haberdar etmemek, ya da tersi, iki arızayı bire katlamak olurdu.
 */
final class SubmitSupportRequest
{
    public function __construct(
        private readonly SupportRequestRepositoryPort $requests,
        private readonly SupportNotifierPort $notifier,
    ) {}

    public function handle(NewSupportRequest $request): SubmittedSupportRequest
    {
        $received = $this->requests->receive($request);

        /*
            `false` = HİÇ DENENMEDİ (taşıyıcı ya da adres yok): damga da
            sebep de yazılmaz ve hâl `Unknown` kalır. `null` = devralındı,
            dize = denendi ve düştü. Üç hâl üç ayrı gerçektir ve ikisine
            indirmek birini yalan yapardı.
        */
        $acknowledgement = $this->notifier->acknowledge($received);

        if ($acknowledgement !== false) {
            $this->requests->recordAcknowledgementOutcome($received->id, $acknowledgement);
        }

        $notification = $this->notifier->notifyOwner($received);

        if ($notification !== false) {
            $this->requests->recordNotificationOutcome($received->id, $notification);
        }

        return new SubmittedSupportRequest(
            $received,
            match (true) {
                $acknowledgement === null => DeliveryState::Sent,
                $acknowledgement === false => DeliveryState::Unknown,
                default => DeliveryState::Failed,
            },
        );
    }
}
