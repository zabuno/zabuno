<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\PaymentTransaction;
use App\Application\Billing\Exception\BillingProfileMissingException;
use App\Application\Billing\Exception\CheckoutConflictException;
use App\Application\Billing\Exception\PaymentGatewayBadGatewayException;
use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Billing\Exception\PaymentTransactionNotFoundException;
use App\Application\Billing\Exception\PlanNotPurchasableException;
use App\Application\Billing\Exception\RefundNotAllowedException;
use App\Application\Billing\Exception\RefundRejectedException;
use App\Application\Billing\Exception\SellerIdentityMissingException;
use App\Application\Billing\Exception\SubscriptionActionNotAllowedException;
use App\Application\Billing\Port\BillingModePort;
use App\Application\Billing\Port\BillingProfileRepositoryPort;
use App\Application\Billing\Port\PaymentGatewaySelectorPort;
use App\Application\Billing\Port\PaymentTransactionRepositoryPort;
use App\Application\Billing\Port\SubscriptionRepositoryPort;
use App\Application\Ledger\Port\LedgerPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformAuditPort;
use App\Domain\Billing\BillingMode;
use App\Domain\Billing\SubscriptionPhase;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Money\LedgerEntry;
use App\Domain\Money\Money;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kendi kendine abonelik: plan seç → fatura profili → ödeme → abonelik
 * (docs/107 Faz 1.1 + 1.3, docs/110 P1-02 (a)).
 *
 * `ManageIyzicoSandboxCheckout`'tan ÜÇ farkı var ve üçü de ürün farkı:
 * plan istekten gelir (aktif aboneliğin planı değil), başarı aboneliği
 * oluşturur/uzatır (yalnız defter yazmaz), ve geçit kipe göre seçilir.
 * Eski kullanım örneği DOKUNULMADAN durur: onun testleri ve yüzeyi
 * dondurulmuştur (`docs/123`).
 *
 * Kadıköy'deki kebapçı için: "Pro"yu seçer, profili tamsa Iyzico'ya
 * gider, kartını girer, geri döner; abonelik 30 gün ileri gitmiştir ve
 * defterde bir satır vardır. Kart reddedilirse burada neden yazar ve yeni
 * bir denemeyle yol tekrar açılır.
 */
final class ManageCheckout
{
    public function __construct(
        private readonly PaymentTransactionRepositoryPort $transactions,
        private readonly BillingProfileRepositoryPort $profiles,
        private readonly SubscriptionRepositoryPort $subscriptions,
        private readonly PaymentGatewaySelectorPort $gateways,
        private readonly BillingModePort $mode,
        private readonly LedgerPort $ledger,
        private readonly ManageInvoices $invoices,
        private readonly PlatformAuditPort $audit,
        private readonly CredentialResolverPort $credentials,
        private readonly ConfigRepository $config,
    ) {}

    /** @return array{mode: string, period_days: int, profile_complete: bool, latest: array<string, mixed>|null} */
    public function status(int $workspaceId): array
    {
        return [
            'mode' => $this->mode->effective()->value,
            'period_days' => $this->periodDays(),
            'profile_complete' => $this->profiles->find($workspaceId) !== null,
            'latest' => $this->transactions->latestFor($workspaceId)?->toArray(),
        ];
    }

    /**
     * @throws PlanNotPurchasableException
     * @throws BillingProfileMissingException
     * @throws SellerIdentityMissingException
     * @throws CheckoutConflictException
     * @throws PaymentGatewayUnavailableException
     * @throws PaymentGatewayBadGatewayException
     */
    public function checkout(int $workspaceId, int $actorUserId, int $planId, string $idempotencyKey): PaymentTransaction
    {
        $plan = $this->transactions->purchasablePlan($planId);

        if ($plan === null) {
            throw new PlanNotPurchasableException('Plan is not active or has no price.');
        }

        $this->refuseSilentDowngrade($workspaceId, $planId, $plan['amount_minor']);

        // Alıcı bilgisi UYDURULMAZ: profil yoksa sağlayıcı hiç çağrılmaz.
        $profile = $this->profiles->find($workspaceId);

        if ($profile === null) {
            throw new BillingProfileMissingException('Billing profile is missing.');
        }

        $mode = $this->mode->effective();

        /*
            SATICISI OLMAYAN SÖZLEŞME KURULMAZ (FF-216).

            Alıcının bilgisi zorunlu (`BillingProfileMissingException`) ama
            SATICININ bilgisi bugüne kadar hiç sorulmuyordu: şirket alanları
            `.env`'de boşken mesafeli satış sözleşmesi tarafını sekiz yerde
            "not yet provided" diye yazıyor ve o metin ödeme adımında kabul
            ediliyordu. Bir sözleşmenin iki tarafı vardır; birini uydurmamak
            yetmez, eksik bırakıldığında da satış yapılmamalıdır.

            YALNIZ CANLI KİPTE. Sandbox bir provadır ve prova, sahip henüz
            şirketini kurmamışken de yapılabilmeli — kapıyı oraya da koymak,
            ürünün denenmesini sahibin noter işine bağlamak olurdu. Etkin
            kip zaten üç kapılıdır (`docs/123`): canlı kipteyiz demek, gerçek
            para hareket edecek demektir.
        */
        if ($mode === BillingMode::Live && ! CompanyProfile::fromConfig()->isComplete()) {
            throw new SellerIdentityMissingException('The seller\'s legal identity is not published; a distance sales agreement cannot be concluded.');
        }
        $conversationId = (string) Str::uuid();

        $claimed = $this->transactions->claim(
            $workspaceId,
            $actorUserId,
            $planId,
            $mode,
            $idempotencyKey,
            $conversationId,
            $plan['amount_minor'],
            $plan['currency'],
            $this->periodDays(),
        );

        if (! $claimed) {
            $raced = $this->transactions->findByIdempotencyKey($idempotencyKey);

            if ($raced !== null && $raced->workspaceId === $workspaceId && $raced->planId === $planId) {
                return $raced;
            }

            throw new CheckoutConflictException('idempotency_key already used for a different checkout.');
        }

        try {
            $result = $this->gateways->forMode($mode)->initializeCheckout(
                $conversationId,
                $workspaceId,
                $actorUserId,
                $plan['amount_minor'],
                $plan['currency'],
                $profile,
            );
        } catch (Throwable $exception) {
            /*
                Sağlayıcıya ulaşılamayan bir deneme sonsuza dek "ayrılmış"
                kalmaz: başarısız yazılır ki panel nedenini söylesin ve sahip
                yeni bir anahtarla tekrar denesin. Aynı anahtar bu satırı
                döndürmeye devam eder — o deneme gerçekten bitmiştir.
            */
            $this->failReservation($idempotencyKey, 'provider_unavailable: '.$exception->getMessage());

            if ($exception instanceof PaymentGatewayUnavailableException || $exception instanceof PaymentGatewayBadGatewayException) {
                throw $exception;
            }

            throw new PaymentGatewayBadGatewayException('Payment provider failed during checkout initialization.', 0, $exception);
        }

        if (
            ($result['signature_valid'] ?? false) !== true
            || ($result['conversation_id'] ?? null) !== $conversationId
            || ! is_string($result['token'] ?? null) || $result['token'] === ''
            || ! is_string($result['redirect_url'] ?? null) || ! str_starts_with($result['redirect_url'], 'https://')
        ) {
            $this->failReservation($idempotencyKey, 'untrusted_initialization_result');

            throw new PaymentGatewayBadGatewayException('Payment provider returned an untrusted checkout initialization result.');
        }

        return $this->transactions->complete($idempotencyKey, $result['token'], $result['redirect_url']);
    }

    /**
     * Üretim webhook'u. Sandbox ucuyla AYNI imza formülü; gizli anahtar
     * işlemin kipine göre — sandbox için `.env`, canlı için KASA.
     *
     * @param  array<string, mixed>  $payload
     * @return int HTTP durum kodu
     */
    public function receiveWebhook(array $payload, ?string $signature): int
    {
        foreach (['iyziEventType', 'iyziPaymentId', 'token', 'paymentConversationId', 'status', 'iyziReferenceCode'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || $payload[$field] === '') {
                return 400;
            }
        }

        if (! is_string($signature) || $signature === '') {
            return 400;
        }

        if ($payload['iyziEventType'] !== 'CHECKOUT_FORM_AUTH' || ! in_array($payload['status'], ['SUCCESS', 'FAILURE'], true)) {
            return 400;
        }

        $verifiedMode = null;

        foreach ($this->webhookSecrets() as $mode => $secret) {
            $expected = hash_hmac(
                'sha256',
                $secret.$payload['iyziEventType'].$payload['iyziPaymentId'].$payload['token'].$payload['paymentConversationId'].$payload['status'],
                $secret,
            );

            if (hash_equals($expected, strtolower($signature))) {
                $verifiedMode = $mode;
                break;
            }
        }

        if ($verifiedMode === null) {
            return 400;
        }

        $transaction = $this->transactions->findByConversationId($payload['paymentConversationId']);

        if ($transaction === null) {
            return 404;
        }

        // Sandbox anahtarıyla imzalanmış bir "canlı" bildirim geçersizdir.
        if ($transaction->mode !== $verifiedMode) {
            return 400;
        }

        if ($transaction->referenceCode === $payload['iyziReferenceCode'] || $transaction->isTerminal()) {
            return 200;
        }

        if (! is_string($transaction->token) || $transaction->token === '' || ! hash_equals($transaction->token, $payload['token'])) {
            return 422;
        }

        $result = $this->gateways->forMode(BillingMode::from($transaction->mode))
            ->retrieveCheckout($payload['token'], $payload['paymentConversationId']);

        if (! $this->resultMatches($result, $transaction)) {
            return 422;
        }

        return $this->settle($transaction, $result, $payload['iyziReferenceCode'], $payload['token']) ? 200 : 422;
    }

    /**
     * Tarayıcı geri dönüşü: sahip kartını girip panele döner. Jeton bilinmiyor
     * ya da işlem zaten bitmişse hiçbir şey yapılmaz; çağıran her durumda
     * güvenli adrese yönlendirir. Sağlayıcı o an cevap veremezse webhook
     * daha sonra kapatır.
     */
    public function receiveCallback(?string $token): void
    {
        if (! is_string($token) || $token === '') {
            return;
        }

        $transaction = $this->transactions->findByToken($token);

        if ($transaction === null || $transaction->isTerminal()) {
            return;
        }

        try {
            $result = $this->gateways->forMode(BillingMode::from($transaction->mode))
                ->retrieveCheckout($token, $transaction->conversationId);
        } catch (Throwable) {
            return;
        }

        if (! $this->resultMatches($result, $transaction)) {
            return;
        }

        $this->settle($transaction, $result, null, $token);
    }

    /**
     * İade — süperadmin, sebeple. Politika: iade edilen DÖNEM düşülür.
     *
     * @throws PaymentTransactionNotFoundException
     * @throws RefundNotAllowedException
     * @throws RefundRejectedException
     */
    public function refund(int $workspaceId, int $transactionId, int $actorUserId, string $reason): PaymentTransaction
    {
        $transaction = $this->transactions->findForWorkspace($workspaceId, $transactionId);

        if ($transaction === null) {
            throw new PaymentTransactionNotFoundException('Transaction not found.');
        }

        if ($transaction->state !== 'succeeded') {
            throw new RefundNotAllowedException('Only a succeeded payment can be refunded.');
        }

        if ($transaction->paymentTransactionId === null || $transaction->paymentTransactionId === '') {
            throw new RefundNotAllowedException('The provider transaction id is unknown; refund it in the provider panel.');
        }

        $result = $this->gateways->forMode(BillingMode::from($transaction->mode))->refund(
            $transaction->conversationId,
            $transaction->paymentTransactionId,
            $transaction->amountMinor,
            $transaction->currency,
            $reason,
        );

        if (($result['status'] ?? null) !== 'success') {
            throw new RefundRejectedException((string) ($result['error_message'] ?? 'Refund rejected by the payment provider.'));
        }

        if (! $this->transactions->markRefunded($transaction->id, $reason, $actorUserId)) {
            throw new RefundNotAllowedException('Only a succeeded payment can be refunded.');
        }

        $window = $this->subscriptions->shortenAfterRefund($transaction->workspaceId, $transaction->periodDays);
        $this->recordRefund($transaction);
        /*
            Fatura SİLİNMEZ: iadenin karşılığı ayrı bir belgedir ve aynı
            seriden sıradaki numarayı alır (docs/130 §K3). Defterdeki ters
            kayıtla aynı karar — hem satış hem iadesi görünür kalır.
        */
        $this->invoices->ensureCreditNoteForRefund($transaction);
        $this->audit->record(
            'billing.refund',
            'refunded',
            'workspace:'.$transaction->workspaceId.'/transaction:'.$transaction->id,
            [
                'amount_minor' => $transaction->amountMinor,
                'currency' => $transaction->currency,
                'mode' => $transaction->mode,
                'reason' => $reason,
                'subscription_ends_at_before' => $window['before'],
                'subscription_ends_at_after' => $window['after'],
            ],
            $actorUserId,
        );

        return $this->transactions->findForWorkspace($workspaceId, $transactionId) ?? $transaction;
    }

    // --- iç yardımcılar ---------------------------------------------------

    /**
     * ÖDENMİŞ DÖNEMİN ORTASINDA UCUZ PLANI SATIN ALMAK, SESSİZ BİR DÜŞÜRMEDİR.
     *
     * `extendFromPayment` ödenen planı geçerli plan yapar. Bu, yükseltmede
     * doğrudur ve istenen şeydir; düşürmede ise sahip PARA ÖDER ve karşılığında
     * o anda bir yetenek KAYBEDER — üstelik ödediği Pro döneminin günleri hâlâ
     * dururken. Ürünün bu ailedeki tek kusuru buydu (`docs/134` §2).
     *
     * Düşürmenin tek yolu zamanlanmış düşürmedir: dönem sonunda yürürlüğe
     * girer, hiçbir gün eksilmez, iade doğmaz. Dönem BİTMİŞSE (ödemesiz süre
     * ya da askı) korunacak bir dönem yoktur ve ucuz plan doğrudan satın
     * alınabilir — o zaten yeni bir başlangıçtır.
     *
     * @throws SubscriptionActionNotAllowedException
     */
    private function refuseSilentDowngrade(int $workspaceId, int $planId, int $amountMinor): void
    {
        $subscription = $this->subscriptions->currentSubscription($workspaceId);

        if ($subscription->planId === null || $subscription->planId === $planId) {
            return;
        }

        if ($subscription->phase !== SubscriptionPhase::Active && $subscription->phase !== SubscriptionPhase::Cancelling) {
            return;
        }

        $currentAmount = $this->transactions->purchasablePlan($subscription->planId)['amount_minor'] ?? null;

        if ($currentAmount !== null && $amountMinor < $currentAmount) {
            throw SubscriptionActionNotAllowedException::downgradeRequiresSchedule();
        }
    }

    /** @param array<string, mixed> $result */
    private function resultMatches(array $result, PaymentTransaction $transaction): bool
    {
        return ($result['signature_valid'] ?? false) === true
            && ($result['conversation_id'] ?? null) === $transaction->conversationId
            && ($result['amount_minor'] ?? null) === $transaction->amountMinor
            && ($result['currency'] ?? null) === $transaction->currency;
    }

    /**
     * Uç duruma geçiş — tam olarak BİR kez. Webhook ile callback aynı anda
     * gelse bile koşullu güncelleme yalnız birine true döner; abonelik ve
     * defter yalnız o birinde yazılır.
     *
     * @param  array<string, mixed>  $result
     */
    private function settle(PaymentTransaction $transaction, array $result, ?string $referenceCode, string $token): bool
    {
        $state = match ($result['status'] ?? null) {
            'SUCCESS' => 'succeeded',
            'FAILURE' => 'failed',
            default => null,
        };

        if ($state === null) {
            return false;
        }

        $paymentId = (string) ($result['payment_id'] ?? '');

        if ($state === 'failed') {
            $reason = $result['error_message'] ?? null;
            $this->transactions->markFailed($transaction->id, $referenceCode, $paymentId, $token, is_string($reason) ? $reason : null);

            return true;
        }

        $paymentTransactionId = $result['payment_transaction_id'] ?? null;
        $transitioned = $this->transactions->markSucceeded(
            $transaction->id,
            $referenceCode,
            $paymentId,
            is_string($paymentTransactionId) ? $paymentTransactionId : null,
            $token,
        );

        if ($transitioned) {
            $window = $this->subscriptions->extendFromPayment($transaction->workspaceId, $transaction->planId, $transaction->periodDays);
            $this->transactions->recordSubscriptionWindow($transaction->id, $window['before'], $window['after']);
            $this->recordRevenue($transaction);
        }

        /*
            BELGE, geçiş bu çağrıda olmasa bile güvenceye alınır.

            Webhook ile tarayıcı geri dönüşü aynı ödeme için sırayla gelir ve
            yalnız biri geçişi yapar. Faturayı yalnız o dala bağlasaydık,
            geçişi yapan çağrı belgeyi yazamadığında (örneğin o an fatura
            profili okunamadığında) ikinci çağrı eksiği hiç kapatamazdı.
            `ensureForPayment` tekrar tekrar çağrılabilir: kesilmiş belge
            yeniden kesilmez.
        */
        $this->invoices->ensureForPayment(
            $this->transactions->findForWorkspace($transaction->workspaceId, $transaction->id) ?? $transaction,
        );

        return true;
    }

    private function recordRevenue(PaymentTransaction $transaction): void
    {
        $reference = 'payment:'.$transaction->id;

        if ($this->ledger->hasReference($transaction->workspaceId, $reference)) {
            return;
        }

        $this->ledger->record(LedgerEntry::record(
            $transaction->workspaceId,
            $reference,
            'cash',
            'revenue',
            Money::fromMinorAmount($transaction->amountMinor, $transaction->currency),
            now()->toDateTimeString(),
            'Subscription payment via Iyzico ('.$transaction->mode.')',
        ));
    }

    /** Defter satırı SİLİNMEZ; iade karşı kayıttır: gelir borç, kasa alacak. */
    private function recordRefund(PaymentTransaction $transaction): void
    {
        $reference = 'payment:'.$transaction->id.':refund';

        if ($this->ledger->hasReference($transaction->workspaceId, $reference)) {
            return;
        }

        $this->ledger->record(LedgerEntry::record(
            $transaction->workspaceId,
            $reference,
            'revenue',
            'cash',
            Money::fromMinorAmount($transaction->amountMinor, $transaction->currency),
            now()->toDateTimeString(),
            'Refund of subscription payment via Iyzico ('.$transaction->mode.')',
        ));
    }

    private function failReservation(string $idempotencyKey, string $reason): void
    {
        $reserved = $this->transactions->findByIdempotencyKey($idempotencyKey);

        if ($reserved !== null) {
            $this->transactions->markFailed($reserved->id, null, null, null, mb_substr($reason, 0, 500));
        }
    }

    /**
     * Kip → webhook gizli anahtarı. Canlı anahtar KASADAN; kasa boşken
     * resolver env'e düşer ve sandbox anahtarını verir — o zaman "canlı"
     * diye ikinci bir aday yoktur, aynı anahtar iki kez denenmez.
     *
     * @return array<string, string>
     */
    private function webhookSecrets(): array
    {
        $secrets = [];
        $sandbox = $this->config->get('services.iyzico.sandbox.secret_key');

        if (is_string($sandbox) && $sandbox !== '') {
            $secrets[BillingMode::Sandbox->value] = $sandbox;
        }

        $live = $this->credentials->resolve(CredentialProvider::Iyzico)['secret_key'] ?? '';

        if (is_string($live) && $live !== '' && $live !== $sandbox) {
            $secrets[BillingMode::Live->value] = $live;
        }

        return $secrets;
    }

    private function periodDays(): int
    {
        $days = (int) $this->config->get('billing.subscription.period_days', 30);

        return $days > 0 ? $days : 30;
    }
}
