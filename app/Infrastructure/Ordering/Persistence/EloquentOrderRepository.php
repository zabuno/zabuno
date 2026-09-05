<?php

declare(strict_types=1);

namespace App\Infrastructure\Ordering\Persistence;

use App\Application\Ordering\Dto\OrderDraft;
use App\Application\Ordering\Exception\OrderPersistenceFailedException;
use App\Application\Ordering\Port\OrderRepositoryPort;
use App\Domain\Ordering\OrderStatus;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class EloquentOrderRepository implements OrderRepositoryPort
{
    public function place(
        int $workspaceId,
        int $locationId,
        int $menuId,
        int $diningTableId,
        ?int $qrCodeId,
        ?int $publicationId,
        string $visitorKey,
        OrderDraft $draft,
        DateTimeImmutable $placedAt,
    ): int {
        try {
            return DB::transaction(function () use (
                $workspaceId,
                $locationId,
                $menuId,
                $diningTableId,
                $qrCodeId,
                $publicationId,
                $visitorKey,
                $draft,
                $placedAt,
            ): int {
                $at = Carbon::instance($placedAt);

                $orderId = (int) DB::table('orders')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'location_id' => $locationId,
                    'menu_id' => $menuId,
                    'dining_table_id' => $diningTableId,
                    'qr_code_id' => $qrCodeId,
                    'publication_id' => $publicationId,
                    'status' => OrderStatus::Pending->value,
                    'visitor_key' => $visitorKey,
                    'total_minor_amount' => $draft->totalMinorAmount,
                    'currency_code' => $draft->currencyCode,
                    'placed_at' => $at,
                    'status_changed_at' => $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                $rows = [];

                foreach ($draft->lines as $line) {
                    $rows[] = [
                        'order_id' => $orderId,
                        'menu_item_id' => $line->menuItemId,
                        'product_name' => $line->productName,
                        'unit_price_minor_amount' => $line->unitPriceMinorAmount,
                        'currency_code' => $line->currencyCode,
                        'quantity' => $line->quantity,
                        'line_total_minor_amount' => $line->lineTotalMinorAmount,
                        'allergens' => json_encode($line->allergens, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'created_at' => $at,
                        'updated_at' => $at,
                    ];
                }

                // TEK `insert`: satır satır yazan bir döngü, ortasında
                // düşerse yarım bir sipariş bırakırdı ve o fiş mutfağa
                // eksik düşerdi.
                DB::table('order_items')->insert($rows);

                return $orderId;
            });
        } catch (QueryException|JsonException $e) {
            throw OrderPersistenceFailedException::fromPrevious($e);
        } catch (Throwable $e) {
            if ($e instanceof OrderPersistenceFailedException) {
                throw $e;
            }

            throw OrderPersistenceFailedException::fromPrevious($e);
        }
    }

    public function openOrderCountForTable(int $workspaceId, int $locationId, int $diningTableId): int
    {
        /*
            AÇIK DURUMLAR LİSTESİ ELLE YAZILMAZ, alandan gelir
            (`OrderStatus::openKeys()`). Buraya kopyalanmış bir liste, yeni
            bir durum eklendiği gün sessizce eksik kalır ve masa sınırı
            yanlış sayar.

            `count(*)` yerine sayaç: PostgreSQL'de takma adsız bir ham
            toplama okunamayan bir sütun adı üretir; Laravel'in `count()`
            kısayolu bunu kendi başına isimlendirir ve iki motorda da aynı
            davranır.
        */
        return DB::table('orders')
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->where('dining_table_id', $diningTableId)
            ->whereIn('status', OrderStatus::openKeys())
            ->count();
    }

    public function statusInScope(int $workspaceId, int $locationId, int $orderId): ?OrderStatus
    {
        $status = DB::table('orders')
            ->where('id', $orderId)
            // Kiracı VE şube sorgunun İÇİNDE: ekran kuralı değil.
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->value('status');

        return $status === null ? null : OrderStatus::tryFrom((string) $status);
    }

    public function transition(
        int $workspaceId,
        int $locationId,
        int $orderId,
        OrderStatus $from,
        OrderStatus $to,
        ?string $rejectionReason,
        DateTimeImmutable $at,
    ): bool {
        try {
            $moment = Carbon::instance($at);

            $values = [
                'status' => $to->value,
                'status_changed_at' => $moment,
                'updated_at' => $moment,
            ];

            /*
                HER AŞAMA KENDİ ANINI YAZAR ve bir daha değişmez. Teslim,
                iptal ve ret aynı `closed_at` sütununu yazar; hangisi olduğunu
                `status` söyler — üç ayrı sütun aynı soruyu üç kez sormak
                olurdu.
            */
            $column = match ($to) {
                OrderStatus::Confirmed => 'confirmed_at',
                OrderStatus::Preparing => 'preparing_at',
                OrderStatus::Ready => 'ready_at',
                OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Rejected => 'closed_at',
                OrderStatus::Pending => null,
            };

            if ($column !== null) {
                $values[$column] = $moment;
            }

            if ($rejectionReason !== null) {
                $values['rejection_reason'] = $rejectionReason;
            }

            /*
                KOŞULLU YAZMA: `WHERE status = $from`. Aradaki mikro saniyede
                başka bir garson onaylamışsa eşleşme olmaz ve `0` döner —
                çağıran katman durumu yeniden okuyup dürüstçe reddeder.
            */
            $affected = DB::table('orders')
                ->where('id', $orderId)
                ->where('workspace_id', $workspaceId)
                ->where('location_id', $locationId)
                ->where('status', $from->value)
                ->update($values);

            return $affected > 0;
        } catch (QueryException $e) {
            throw OrderPersistenceFailedException::fromPrevious($e);
        }
    }
}
