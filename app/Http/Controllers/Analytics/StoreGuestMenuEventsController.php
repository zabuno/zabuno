<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\Publication\MenuPublicAddress;
use App\Http\Controllers\Controller;
use App\Support\Analytics\VisitorKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Misafir sayfasının olay ucu — `docs/84` (P1-08).
 *
 * HERKESE AÇIK, çünkü menü de herkese açık. Sahtecilik üç şeyle sınırlanır:
 * sayılan şey ham vuruş değil FARKLI ZİYARETÇİ; olaylar yalnız YAYINDAKİ
 * satırlar için kabul edilir; istek ve olay sayısı sınırlıdır.
 *
 * Bu kararlı bir saldırganı durdurmaz — durdurduğunu iddia etmek yanlış
 * olur. Durdurduğu şey, sayıların kazayla ya da ucuz bir betikle
 * anlamsızlaşması.
 */
final class StoreGuestMenuEventsController extends Controller
{
    /** Tek istekte kabul edilen en fazla olay. */
    private const MAX_EVENTS = 60;

    /** Arama terimi sütunun taşıyabileceği uzunlukta kırpılır. */
    private const MAX_TERM_LENGTH = 80;

    public function __construct(private readonly AnalyticsRepositoryPort $analytics) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'menuKey' => ['required', 'string'],
            'events' => ['required', 'array', 'max:'.self::MAX_EVENTS],
            'events.*.type' => ['required', 'string'],
            'events.*.menuItemId' => ['sometimes', 'nullable', 'integer'],
            'events.*.term' => ['sometimes', 'nullable', 'string'],
        ]);

        if (! MenuPublicAddress::isKey((string) $validated['menuKey'])) {
            return $this->accepted();
        }

        $menu = DB::table('menus')
            ->where('public_key', (string) $validated['menuKey'])
            ->first();

        if ($menu === null) {
            // Var olmayan bir menü için de AYNI yanıt: bir anahtarın geçerli
            // olup olmadığı, deneyerek öğrenilebilecek bir bilgi olmamalı.
            return $this->accepted();
        }

        $workspaceId = (int) $menu->workspace_id;
        $now = Carbon::now();

        $visitorKey = VisitorKey::forRequest($request, $workspaceId, $now);

        // YAYINDAKİ satırlar: taslakta olan ya da başka bir menüye ait bir
        // kimlik buraya yazılamaz.
        $publishedItemIds = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->where('menu_categories.menu_id', $menu->id)
            ->where('menu_items.is_visible', true)
            ->pluck('menu_items.id')
            ->map(static fn ($id): int => (int) $id)
            ->flip();

        $seenThisRequest = [];

        foreach ($validated['events'] as $event) {
            $type = (string) ($event['type'] ?? '');

            if ($type === AnalyticsEventType::ItemView->value) {
                $menuItemId = (int) ($event['menuItemId'] ?? 0);

                if (! $publishedItemIds->has($menuItemId)) {
                    continue;
                }

                // Aynı istekte tekrar eden kimlik ve aynı gün zaten sayılmış
                // olan görüntülenme bir kere sayılır: sayılan şey İLGİ.
                if (isset($seenThisRequest[$menuItemId])) {
                    continue;
                }

                $seenThisRequest[$menuItemId] = true;

                if ($this->analytics->itemViewAlreadyCounted($workspaceId, $menuItemId, $visitorKey, $now)) {
                    continue;
                }

                $this->analytics->record(
                    $workspaceId,
                    (int) $menu->location_id,
                    null,
                    (int) $menu->id,
                    AnalyticsEventType::ItemView,
                    $now,
                    $visitorKey,
                    $menuItemId,
                );

                continue;
            }

            if ($type === AnalyticsEventType::SearchNoResults->value) {
                $term = $this->normalizeTerm((string) ($event['term'] ?? ''));

                if ($term === '') {
                    continue;
                }

                $this->analytics->record(
                    $workspaceId,
                    (int) $menu->location_id,
                    null,
                    (int) $menu->id,
                    AnalyticsEventType::SearchNoResults,
                    $now,
                    $visitorKey,
                    null,
                    $term,
                );
            }
        }

        return $this->accepted();
    }

    /**
     * Terim küçültülür ve KIRPILIR — reddedilmez.
     *
     * Uzun bir terim yüzünden isteği reddetmek, misafirin farkında bile
     * olmadığı bir hata üretirdi; oysa bu ölçüm onun için değil sahip için
     * yapılıyor.
     */
    private function normalizeTerm(string $term): string
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        if ($term === '') {
            return '';
        }

        return mb_substr(mb_strtolower($term, 'UTF-8'), 0, self::MAX_TERM_LENGTH);
    }

    /**
     * Yanıt HER ZAMAN aynı: ölçüm misafirin işi değil ve bir hata onun
     * ekranında görünmemeli.
     */
    private function accepted(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
