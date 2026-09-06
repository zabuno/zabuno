<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Application\Support\Dto\NewSupportRequest;
use App\Application\Support\UseCase\SubmitSupportRequest;
use App\Domain\Support\SupportChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreWorkspaceSupportRequestRequest;
use Illuminate\Http\JsonResponse;

/**
 * Panelden yeni destek talebi — FF-201 (`docs/125` §4).
 *
 * AD VE E-POSTA HESAPTAN GELİR, gövdeden değil. Oturum açmış birine adını
 * yeniden sormak gereksiz; gövdeden kabul etmek ise başkasının adıyla talep
 * açmanın yolu olurdu. Talep çalışma alanına ve kişiye bağlanır: süperadmin
 * "hangi restoran, kim yazdı" sorusunu satırdan okur.
 */
final class StoreWorkspaceSupportRequestController extends Controller
{
    public function __construct(
        private readonly SubmitSupportRequest $submit,
    ) {}

    public function __invoke(StoreWorkspaceSupportRequestRequest $request, int $workspace): JsonResponse
    {
        $user = $request->user();

        $submitted = $this->submit->handle(new NewSupportRequest(
            name: (string) $user->name,
            email: (string) $user->email,
            subject: (string) $request->validated('subject'),
            message: (string) $request->validated('message'),
            channel: SupportChannel::Panel,
            locale: app()->getLocale(),
            workspaceId: $workspace,
            userId: (int) $user->getKey(),
        ));

        return response()->json($submitted->toArray(), 201);
    }
}
