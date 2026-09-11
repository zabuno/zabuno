<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Port\SubscriptionGraceReminderRepositoryPort;
use App\Domain\Tenancy\MembershipRole;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ödemesiz süre hatırlatmasının deposu — `docs/134`.
 *
 * DAMGA ANAHTARI HER ZAMAN AYNI BİÇİMDE YAZILIR (`Y-m-d H:i:s`). Sürücüden
 * sürücüye değişen bir zaman biçimlendirmesi, yazarken bir dizeye okurken
 * başka bir dizeye dönüşen bir anahtar demektir: aynı sahip aynı dönemde
 * ikinci postayı alır ve sebebi hiçbir yerde görünmezdi.
 */
final readonly class EloquentSubscriptionGraceReminderRepository implements SubscriptionGraceReminderRepositoryPort
{
    private const TABLE = 'subscription_grace_reminders';

    private const STAMP_FORMAT = 'Y-m-d H:i:s';

    public function candidates(DateTimeInterface $endedAfter, DateTimeInterface $endedBefore): array
    {
        $rows = DB::table('subscriptions')
            ->where('ends_at', '<', $this->stamp($endedBefore))
            ->where('ends_at', '>=', $this->stamp($endedAfter))
            ->orderBy('workspace_id')
            ->get(['workspace_id', 'ends_at', 'cancelled_at']);

        $candidates = [];

        foreach ($rows as $row) {
            $candidates[] = [
                'workspaceId' => (int) $row->workspace_id,
                'endsAt' => Carbon::parse($row->ends_at)->toDateTimeImmutable(),
                'cancelledAt' => $row->cancelled_at === null
                    ? null
                    : Carbon::parse($row->cancelled_at)->toDateTimeImmutable(),
            ];
        }

        return $candidates;
    }

    public function currentOwnerEmails(int $workspaceId): array
    {
        /*
            BUGÜNKÜ sahipler — üyelik tablosundan. `workspaces.created_by`
            DEĞİL: kuran kişi devredip çıkmış olabilir ve o zaman hatırlatma,
            artık ödeme yapamayan birine giderdi.
        */
        $emails = DB::table('workspace_memberships')
            ->join('users', 'users.id', '=', 'workspace_memberships.user_id')
            ->where('workspace_memberships.workspace_id', $workspaceId)
            ->where('workspace_memberships.role', MembershipRole::Owner->value)
            ->orderBy('users.email')
            ->pluck('users.email');

        $recipients = [];

        foreach ($emails as $email) {
            $address = trim((string) $email);

            if ($address !== '') {
                $recipients[] = $address;
            }
        }

        return array_values(array_unique($recipients));
    }

    public function alreadyNotified(int $workspaceId, DateTimeInterface $periodEndsAt, string $recipientEmail): bool
    {
        return DB::table(self::TABLE)
            ->where('workspace_id', $workspaceId)
            ->where('period_ends_at', $this->stamp($periodEndsAt))
            ->where('recipient_email', $recipientEmail)
            ->exists();
    }

    public function markNotified(int $workspaceId, DateTimeInterface $periodEndsAt, string $recipientEmail): void
    {
        $now = Carbon::now();

        /*
            `insertOrIgnore`: benzersiz indeksle çakışma bir arıza değil,
            aynı işin iki kez istenmesidir. Üst üste binen iki koşuda
            ikincisinin istisna atması, ilk koşunun başarıyla gönderdiği
            postayı "düşmüş" göstermek olurdu.
        */
        DB::table(self::TABLE)->insertOrIgnore([
            'workspace_id' => $workspaceId,
            'period_ends_at' => $this->stamp($periodEndsAt),
            'recipient_email' => $recipientEmail,
            'sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function stamp(DateTimeInterface $moment): string
    {
        return Carbon::instance(DateTimeImmutable::createFromInterface($moment))->format(self::STAMP_FORMAT);
    }
}
