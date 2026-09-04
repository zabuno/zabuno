<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\QrDestination\QrTheme;
use App\Http\Controllers\Controller;
use App\Support\QrDestination\QrLayoutResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;

final class ExportQrCodePngController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly QrLayoutResolver $layouts,
        private readonly QrCodeImageExportPort $imageExport,
    ) {}

    public function __invoke(Request $request, int $workspace, int $qrCode): Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response('Not Found.', 404);
        }

        $record = $this->qrCodes->findById($qrCode);

        if ($record === null || $record->workspaceId !== $workspace || $record->state !== 'active') {
            return response('Not Found.', 404);
        }

        if (! $this->authorization->can($userId, Permission::QrDesignManage, $workspace)) {
            return response('Forbidden.', 403);
        }

        $validated = $request->validate([
            'theme' => ['sometimes', Rule::in(array_map(static fn (QrTheme $case) => $case->value, QrTheme::cases()))],
        ]);

        /*
            "Markalı" tema markanın GERÇEK rengini kullanır (FF-112) ve renk
            taranabilir değilse klasiğe düşer. Karar tek yerde verilir; dört
            uçta ayrı ayrı çözmek, önizlemenin bir renk, yazıcıdan çıkan
            kartın başka bir renk olması demekti.
        */
        $layout = $this->layouts->resolve($validated['theme'] ?? null, $workspace, $record->menuId);

        try {
            $rendered = $this->imageExport->renderPng(url("/q/{$record->token}"), $layout);
        } catch (RuntimeException $exception) {
            // The body deliberately says nothing about why, so without this
            // the reason for a 500 exists nowhere at all — not in the response
            // and not in the log.
            report($exception);

            return response('QR image generation failed.', 500);
        }

        $headers = ['Content-Type' => $rendered->mimeType];

        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="qr-'.$record->token.'.png"';
        }

        return response($rendered->bytes, 200, $headers);
    }
}
