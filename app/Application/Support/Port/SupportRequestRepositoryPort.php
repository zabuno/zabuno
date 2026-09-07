<?php

declare(strict_types=1);

namespace App\Application\Support\Port;

use App\Application\Support\Dto\NewSupportRequest;
use App\Application\Support\Dto\ReceivedSupportRequest;
use App\Application\Support\Dto\SupportRequestAdminRow;
use App\Application\Support\Dto\SupportRequestSummary;
use App\Application\Support\Exception\SupportReferenceExhaustedException;
use App\Domain\Support\SupportRequestStatus;

interface SupportRequestRepositoryPort
{
    /**
     * Talebi kaydeder ve benzersiz bir referans verir.
     *
     * Referans çarpışırsa (tekil indeks) yeni numara denenir; müşteri bunu
     * hiç görmez. Denemeler tükenirse gürültüyle durur — sonsuz döngü de,
     * referanssız bir kayıt da kabul edilmez.
     *
     * @throws SupportReferenceExhaustedException
     */
    public function receive(NewSupportRequest $request): ReceivedSupportRequest;

    /**
     * Bu çalışma alanının talepleri, EN YENİ ÜSTTE: sahip son açtığı
     * talebin durumunu arar.
     *
     * @return list<SupportRequestSummary>
     */
    public function listByWorkspaceId(int $workspaceId): array;

    /**
     * Alındı bildiriminin sonucu. `null` = taşıyıcı devraldı; aksi hâlde
     * kırpılmış sebep. Sebep API'ye asla çıkmaz.
     */
    public function recordAcknowledgementOutcome(int $id, ?string $failure): void;

    /** Sahibe bildirimin sonucu — aynı sözleşme. */
    public function recordNotificationOutcome(int $id, ?string $failure): void;

    /**
     * Süperadmin kuyruğu: her kanal, isteğe bağlı durum süzgeci, EN ESKİ
     * ÜSTTE — bir kuyruk sırayla boşaltılır.
     *
     * @return list<SupportRequestAdminRow>
     */
    public function listForPlatform(?SupportRequestStatus $status): array;

    /**
     * Durumu değiştirir. `answered`'a ilk geçişte `first_response_at`
     * damgalanır ve bir daha DEĞİŞMEZ. Satır yoksa `null` döner.
     */
    public function changeStatus(int $id, SupportRequestStatus $status): ?SupportRequestSummary;
}
