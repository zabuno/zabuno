<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Persistence;

use App\Application\Support\Dto\NewSupportRequest;
use App\Application\Support\Dto\ReceivedSupportRequest;
use App\Application\Support\Dto\SupportReplyTarget;
use App\Application\Support\Dto\SupportRequestAdminRow;
use App\Application\Support\Dto\SupportRequestSummary;
use App\Application\Support\Exception\SupportReferenceExhaustedException;
use App\Application\Support\Port\SupportReferenceGeneratorPort;
use App\Application\Support\Port\SupportRequestRepositoryPort;
use App\Domain\Support\DeliveryState;
use App\Domain\Support\SupportChannel;
use App\Domain\Support\SupportRequestStatus;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentSupportRequestRepository implements SupportRequestRepositoryPort
{
    /**
     * Kaç kez yeni referans denenir. Beş çarpışma üst üste, 28 milyonluk
     * bir alanda rastlantı değil arızadır — o noktada durmak doğrudur.
     */
    public const MAX_REFERENCE_ATTEMPTS = 5;

    public function __construct(
        private readonly SupportReferenceGeneratorPort $references,
    ) {}

    public function receive(NewSupportRequest $request): ReceivedSupportRequest
    {
        $receivedAt = Carbon::now();

        for ($attempt = 1; $attempt <= self::MAX_REFERENCE_ATTEMPTS; $attempt++) {
            $reference = $this->references->generate();

            try {
                /*
                    HER DENEME KENDİ KAYIT NOKTASINDA (savepoint). PostgreSQL,
                    başarısız bir INSERT'ten sonra dış işlemi iptal eder
                    (SQLSTATE 25P02) ve aynı işlemde atılan ikinci deneme
                    çarpışma değil "işlem iptal edildi" hatasıyla düşer —
                    CI'da ölçüldü. İç `transaction()` Laravel'de bir SAVEPOINT
                    açar; reddedilen deneme o noktaya geri sarılır, dış
                    işlem (ve RefreshDatabase'in testi saran işlemi) yaşar.
                    SQLite bu farkı göstermiyordu; PostgreSQL üretim hedefi.
                */
                $id = (int) DB::transaction(fn (): int => (int) DB::table('support_requests')->insertGetId([
                    'reference' => $reference,
                    'workspace_id' => $request->workspaceId,
                    'user_id' => $request->userId,
                    'name' => $request->name,
                    'email' => $request->email,
                    'subject' => $request->subject,
                    'message' => $request->message,
                    'channel' => $request->channel->value,
                    'status' => SupportRequestStatus::Received->value,
                    'locale' => $request->locale,
                    'received_at' => $receivedAt,
                    'created_at' => $receivedAt,
                    'updated_at' => $receivedAt,
                ]));
            } catch (QueryException $exception) {
                /*
                    ÇARPIŞMA SESSİZCE ÇÖZÜLÜR. Tekil indeks aynı referansı
                    reddetti; yeni numarayla yeniden denenir. Başka her
                    hata olduğu gibi yükselir — "tablo yok"u bir çarpışma
                    sanıp beş kez denemek, gerçek arızayı beş kat
                    geciktirmek olurdu.
                */
                if (self::isUniqueViolation($exception)) {
                    continue;
                }

                throw $exception;
            }

            return new ReceivedSupportRequest(
                $id,
                $reference,
                $request->name,
                $request->email,
                $request->subject,
                $request->message,
                $request->channel,
                $request->locale,
                $request->workspaceId,
                DateTimeImmutable::createFromInterface($receivedAt),
            );
        }

        throw new SupportReferenceExhaustedException(sprintf(
            'Could not allocate a unique support reference after %d attempts.',
            self::MAX_REFERENCE_ATTEMPTS,
        ));
    }

    /**
     * SQLSTATE 23000 (SQLite, MySQL) ve 23505 (PostgreSQL) — ikisi de
     * bütünlük ihlalidir. PDO kodu DİZE olarak taşır; `(string)` karşılaştırma
     * bir sürücünün sayı döndürdüğü gün de doğru kalsın diye.
     */
    public static function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }

    /** @return list<SupportRequestSummary> */
    public function listByWorkspaceId(int $workspaceId): array
    {
        return DB::table('support_requests')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('id')
            ->select(['id', 'reference', 'subject', 'status', 'received_at', 'first_response_at'])
            ->get()
            ->map(fn (object $row): SupportRequestSummary => self::summaryFromRow($row))
            ->all();
    }

    public function recordAcknowledgementOutcome(int $id, ?string $failure): void
    {
        DB::table('support_requests')->where('id', $id)->update([
            'acknowledged_at' => $failure === null ? now() : null,
            'acknowledgement_failure' => $failure,
            'updated_at' => now(),
        ]);
    }

    public function recordNotificationOutcome(int $id, ?string $failure): void
    {
        DB::table('support_requests')->where('id', $id)->update([
            'notified_at' => $failure === null ? now() : null,
            'notification_failure' => $failure,
            'updated_at' => now(),
        ]);
    }

    /** @return list<SupportRequestAdminRow> */
    public function listForPlatform(?SupportRequestStatus $status): array
    {
        $query = DB::table('support_requests')->orderBy('id');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query
            ->get()
            ->map(static fn (object $row): SupportRequestAdminRow => new SupportRequestAdminRow(
                (int) $row->id,
                (string) $row->reference,
                $row->workspace_id === null ? null : (int) $row->workspace_id,
                (string) $row->name,
                (string) $row->email,
                (string) $row->subject,
                (string) $row->message,
                SupportChannel::from((string) $row->channel)->value,
                (string) $row->status,
                self::iso($row->received_at),
                $row->first_response_at === null ? null : self::iso($row->first_response_at),
                DeliveryState::fromRow($row->acknowledged_at, $row->acknowledgement_failure),
            ))
            ->all();
    }

    public function findReplyTarget(int $id): ?SupportReplyTarget
    {
        $row = DB::table('support_requests')
            ->where('id', $id)
            ->select(['id', 'reference', 'email', 'name', 'subject', 'locale'])
            ->first();

        return $row === null ? null : new SupportReplyTarget(
            (int) $row->id,
            (string) $row->reference,
            (string) $row->email,
            (string) $row->name,
            (string) $row->subject,
            $row->locale === null ? null : (string) $row->locale,
        );
    }

    public function changeStatus(int $id, SupportRequestStatus $status): ?SupportRequestSummary
    {
        return DB::transaction(function () use ($id, $status): ?SupportRequestSummary {
            $row = DB::table('support_requests')->where('id', $id)->lockForUpdate()->first();

            if ($row === null) {
                return null;
            }

            $update = ['status' => $status->value, 'updated_at' => now()];

            // İLK yanıt: yalnız damga BOŞKEN atılır. Kapanıp yeniden
            // cevaplanan talep ilk yanıt zamanını korur.
            if ($status === SupportRequestStatus::Answered && $row->first_response_at === null) {
                $update['first_response_at'] = now();
            }

            DB::table('support_requests')->where('id', $id)->update($update);

            $fresh = DB::table('support_requests')
                ->where('id', $id)
                ->select(['id', 'reference', 'subject', 'status', 'received_at', 'first_response_at'])
                ->first();

            return $fresh === null ? null : self::summaryFromRow($fresh);
        });
    }

    private static function summaryFromRow(object $row): SupportRequestSummary
    {
        return new SupportRequestSummary(
            (int) $row->id,
            (string) $row->reference,
            (string) $row->subject,
            (string) $row->status,
            self::iso($row->received_at),
            $row->first_response_at === null ? null : self::iso($row->first_response_at),
        );
    }

    /**
     * Zaman damgası API'ye ISO-8601 olarak çıkar; sürücünün kendi biçimi
     * (SQLite `Y-m-d H:i:s`, PostgreSQL mikrosaniyeli) ekrana sızmaz.
     */
    private static function iso(mixed $value): string
    {
        return Carbon::parse((string) $value)->toIso8601String();
    }
}
