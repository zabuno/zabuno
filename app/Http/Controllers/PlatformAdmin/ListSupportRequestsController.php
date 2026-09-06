<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Support\Dto\SupportRequestAdminRow;
use App\Application\Support\UseCase\ListPlatformSupportRequests;
use App\Domain\Support\SupportRequestStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Süperadmin destek kuyruğu — FF-201 (`docs/125` §5). Ekranı henüz yok.
 *
 * Tanınmayan bir `status` süzgeci HATA DEĞİL, süzgeçsiz listedir: kuyruğa
 * bakan biri yanlış bir kelime yüzünden boş ekran görmemeli.
 */
final class ListSupportRequestsController extends Controller
{
    public function __construct(
        private readonly ListPlatformSupportRequests $listRequests,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $status = SupportRequestStatus::tryFrom((string) $request->query('status', ''));

        return response()->json(array_map(
            static fn (SupportRequestAdminRow $row): array => $row->toArray(),
            $this->listRequests->handle($status),
        ));
    }
}
