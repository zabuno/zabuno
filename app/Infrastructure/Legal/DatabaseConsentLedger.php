<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal;

use App\Application\Legal\Port\ConsentLedgerPort;
use App\Domain\Legal\ConsentRecord;
use Illuminate\Support\Facades\DB;

/**
 * `consent_records` tablosu — yalnız ekler (FF-198).
 */
final class DatabaseConsentLedger implements ConsentLedgerPort
{
    public function append(ConsentRecord $record): void
    {
        DB::table('consent_records')->insert([
            'user_id' => $record->userId,
            'workspace_id' => $record->workspaceId,
            'kind' => $record->kind,
            'document_key' => $record->documentKey,
            'document_version' => $record->documentVersion,
            'granted' => $record->granted,
            'ip' => $record->ip === null ? null : mb_substr($record->ip, 0, 45),
            // Tarayıcı kimliği sütun sınırına KIRPILIR: bazı tarayıcılar 300
            // karakterlik dizeler gönderir ve kırpılmış bir iz, yazılamayan
            // bir onay kaydından iyidir.
            'user_agent' => $record->userAgent === null ? null : mb_substr($record->userAgent, 0, 255),
            'recorded_at' => $record->recordedAt->format('Y-m-d H:i:s'),
        ]);
    }
}
