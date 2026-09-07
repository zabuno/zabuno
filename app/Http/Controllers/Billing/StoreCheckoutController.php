<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\Exception\BillingProfileMissingException;
use App\Application\Billing\Exception\CheckoutConflictException;
use App\Application\Billing\Exception\PaymentGatewayBadGatewayException;
use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Billing\Exception\PlanNotPurchasableException;
use App\Application\Billing\Exception\SellerIdentityMissingException;
use App\Application\Billing\UseCase\ManageCheckout;
use App\Application\Legal\ConsentRecorder;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Ödeme başlatma. Gövde YALNIZ plan_id, idempotency_key ve İKİ ONAY taşır:
 * tutar, para birimi ya da kart alanı taşıyan bir istek 422 ile geri döner —
 * istemciden gelen tutara güvenilmez (docs/09 §5).
 *
 * ═══ ÖDEME ADIMINDAKİ İKİ ONAY (FF-216) ═══
 *
 * FF-197'de ödeme akışı yazıldı ama sözleşme onayı yoktu: gövde plan ve
 * anahtardan ibaretti, `ConsentRecorder::recordCheckout` hazır durup hiç
 * çağrılmıyordu. Yani mesafeli satış sözleşmesi yayındaydı, ödeme akışı
 * çalışıyordu ve ikisi birbirine hiç değmiyordu.
 *
 * İki ayrı alan, çünkü iki ayrı hukuki olgu:
 *   - `agreements_accepted`: Ön Bilgilendirme Formu ve Mesafeli Satış
 *     Sözleşmesi okundu ve kabul edildi.
 *   - `immediate_performance_accepted`: hizmetin ifasına DERHÂL başlanması
 *     açıkça istendi — bunun cayma hakkı üzerindeki sonucu okunarak.
 *
 * İkisi de `accepted`: alan hiç gelmezse de düşer, `false` gelirse de.
 * Kayıt ekranındaki `terms_accepted` ile aynı kural (`CreateNewUser`);
 * sessizlik onay değildir ve önceden işaretli kutu onay değildir.
 *
 * ONAY, SİPARİŞ GERÇEKTEN BAŞLADIKTAN SONRA YAZILIR. Sağlayıcıya hiç
 * ulaşamamış bir deneme sipariş değildir; onun için defterde satır bırakmak,
 * karşılığı olmayan bir kabul kaydı üretmek olurdu.
 */
final class StoreCheckoutController extends Controller
{
    private const ALLOWED_FIELDS = ['plan_id', 'idempotency_key', 'agreements_accepted', 'immediate_performance_accepted'];

    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageCheckout $checkout,
        private readonly ConsentRecorder $consents,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $unexpected = array_diff(array_keys($request->all()), self::ALLOWED_FIELDS);

        if ($unexpected !== []) {
            return response()->json([
                'message' => 'Only plan_id, idempotency_key and the two consent fields are accepted; the amount is read from the plan on the server.',
                'reason' => 'unexpected_fields',
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'plan_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
            // `accepted` örtük bir kuraldır: alan hiç gelmezse de düşer.
            'agreements_accepted' => ['accepted'],
            'immediate_performance_accepted' => ['accepted'],
        ])->validate();

        try {
            $transaction = $this->checkout->checkout($workspace, $userId, (int) $validated['plan_id'], (string) $validated['idempotency_key']);
        } catch (PlanNotPurchasableException) {
            return response()->json(['message' => 'This plan cannot be purchased.', 'reason' => 'plan_not_purchasable'], 422);
        } catch (BillingProfileMissingException) {
            return response()->json(['message' => 'Billing details are missing.', 'reason' => 'billing_profile_missing'], 422);
        } catch (SellerIdentityMissingException) {
            /*
                SATICI YOKSA SATIŞ YOK (FF-216). Bu bir istemci hatası
                değildir: alıcı her şeyi doğru yaptı, eksik olan satıcının
                kendi yasal kimliği. Panel bunu olduğu gibi söyler ve
                kullanıcıya "bilgilerinizi düzeltin" demez.
            */
            return response()->json(['message' => 'This service cannot take payments yet.', 'reason' => 'seller_identity_missing'], 409);
        } catch (CheckoutConflictException) {
            return response()->json(['message' => 'Conflict.'], 409);
        } catch (PaymentGatewayUnavailableException) {
            return response()->json(['message' => 'Service Unavailable.'], 503);
        } catch (PaymentGatewayBadGatewayException) {
            return response()->json(['message' => 'Bad Gateway.'], 502);
        }

        /*
            ONAY DEFTERİ, SİPARİŞ BAŞLADIKTAN SONRA (FF-198 deseni, FF-216'da
            bağlandı). Sürüm çağırandan değil kütüphaneden okunur; metin
            0.2 olduğu gün kayıt 0.2 yazar (`ConsentRecorder`).

            Aynı `idempotency_key` ile yapılan bir TEKRAR bu satıra ulaşmaz
            mı? Ulaşır — ve ulaşmalıdır: defter yalnız ekler ve aynı siparişe
            ikinci bir kabul satırı düşmesi, kabulün ne zaman verildiğini
            bulanıklaştırmaz, çünkü her satır kendi zamanını taşır.
        */
        $this->consents->recordCheckout(
            $userId,
            $workspace,
            $request,
            // Doğrulama bunu zaten `accepted` istiyor; yine de istekten
            // okunuyor. Sabit bir `true` yazmak, kural bir gün gevşetilirse
            // verilmemiş bir onayı kaydetmek olurdu.
            $request->boolean('immediate_performance_accepted'),
        );

        return response()->json($transaction->toArray(), 202);
    }
}
