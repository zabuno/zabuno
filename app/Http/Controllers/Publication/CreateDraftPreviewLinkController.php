<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * "Telefonda önizle" — taslağın KISA ÖMÜRLÜ, İMZALI adresi.
 *
 * Restoran sahibinin yolculuğu: fiyatları düzeltir ve "masadaki misafir
 * bunu nasıl görecek?" diye bakmak ister. Bugüne kadar bunun tek yolu
 * YAYINLAMAKTI — yani kontrol etmek için önce riski almak. Bu adres o
 * sırayı tersine çevirir: önce bakılır, sonra yayınlanır.
 *
 * Adres MİSAFİRİN ADRESİ DEĞİLDİR ve olamaz. Misafirin kalıcı adresi
 * (`/restoran/.../menu/{key}`) basılı karta gömülüdür ve asla değişmez;
 * önizleme adresi ise on beş dakika sonra ölür. Desen `docs/49` Faz 6'daki
 * asıl görsel indirme bağlantısıyla aynıdır: imza yetkidir, oturum
 * gerekmez — çünkü sahip bu bağlantıyı kendi telefonunda açacaktır ve orada
 * panele girmiş olması beklenemez.
 */
final class CreateDraftPreviewLinkController extends Controller
{
    /**
     * On beş dakika: sahibin telefonu eline alıp menüye bakmasına fazlasıyla
     * yeter, bir grup sohbetine düşen bağlantının ertesi gün çalışmasına
     * yetmez.
     */
    private const LIFETIME_MINUTES = 15;

    public function __construct(
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $expiresAt = now()->addMinutes(self::LIFETIME_MINUTES);

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'publication.draftPreview',
                $expiresAt,
                ['workspace' => $workspace, 'menu' => $menu],
            ),
            'expiresAt' => $expiresAt->toIso8601String(),
        ], 201);
    }
}
