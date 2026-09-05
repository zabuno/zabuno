<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaAuditPort;
use App\Application\Media\Port\MediaLegalHoldPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * YASAL SAKLAMA — kaynağın "Yönetişim" bölümündeki kilit
 * (`docs/reference/panel-v3/MedyaModulu.dc.html`).
 *
 * Kaynağın cümlesi: "Uyuşmazlık kaydına bağlı dosyalar silinemez — toplu
 * işlem bunları atlar." Kilit bir ETİKET değil bir KAPI olmalı; o yüzden
 * bu uç var. Etiket olsaydı toplu işlem dosyayı atlar, tek dosya silme
 * ise kilidi hiç görmeden silerdi.
 *
 * `workspace.manage` ISTER, `media.manage` değil. Sebep: bu kilit medya
 * işi değil HUKUK işidir. `media.manage` editörde de vardır ve editör
 * içerik düzenler; bir uyuşmazlık kaydına bağlı dosyanın kilidini açmak
 * içerik düzenlemek değildir.
 *
 * SEBEP ZORUNLUDUR (kilit konarken). "Kilitli" tek başına, altı ay sonra
 * "neden kilitli?" diye soran sahibe hiçbir şey söylemez; kilidi
 * kaldırmaya cesaret edemez ve dosya sonsuza kadar orada kalır.
 */
final class UpdateMediaLegalHoldController extends Controller
{
    public function __construct(
        private readonly MediaLegalHoldPort $legalHold,
        private readonly AuthorizationPort $authorization,
        private readonly MediaAuditPort $audit,
    ) {}

    public function __invoke(Request $request, int $workspace, int $media): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json([
                'message' => 'Forbidden.',
                'requiredPermission' => Permission::WorkspaceManage->value,
            ], 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $reason = $validated['reason'] ?? null;

        // Yabancı ya da olmayan bir kimlik 404'tür: "geçersiz varlık"
        // demek bile, kimliğin başka bir kiracıda var olup olmadığını
        // sızdırma yolunu açardı.
        if (! $this->legalHold->set($workspace, $media, $reason, $userId)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $placing = $reason !== null && trim($reason) !== '';

        // Kilit koymak ve kaldırmak İZ BIRAKIR ve iz eylem BAŞARILI
        // olduktan sonra yazılır.
        $this->audit->record($workspace, $media, $placing ? 'legal-hold-placed' : 'legal-hold-released', $userId);

        return response()->json([
            'id' => $media,
            'legalHold' => $placing
                ? ['reason' => trim((string) $reason)]
                : null,
        ]);
    }
}
