<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Billing\UseCase\ManageCheckout;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tarayıcı geri dönüşü: sağlayıcının ödeme sayfası, kart girildikten sonra
 * buraya POST eder. Sonuç ne olursa olsun sahip paneline döner; paneldeki
 * durum sunucudan okunur, adresten değil.
 */
final class ReceiveIyzicoCallbackController extends Controller
{
    private const SAFE_REDIRECT_PATH = '/app#billing';

    public function __construct(
        private readonly ManageCheckout $checkout,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->input('token');

        $this->checkout->receiveCallback(is_string($token) ? $token : null);

        return redirect(self::SAFE_REDIRECT_PATH, 303);
    }
}
