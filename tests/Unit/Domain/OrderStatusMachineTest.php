<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Ordering\OrderActor;
use App\Domain\Ordering\OrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * SİPARİŞ DURUM MAKİNESİ — `docs/115` §2 (FF-176 / S1).
 *
 * Bu test çerçeveyi HİÇ ayağa kaldırmaz ve bu bilinçlidir: akışın kemiği
 * bir veritabanı sorusu değil, bir kural sorusudur. Kuralı yalnız uçtan uca
 * bir istekle sınasaydık, kuralın kendisi hiçbir zaman tek bir yerde
 * yazılmamış olurdu — ve ikinci bir yüzey (garson kuyruğu, mutfak monitörü)
 * eklendiğinde her biri kendi kopyasını üretirdi.
 *
 * Sınanan üç şey:
 *
 * 1. Çizilen akış (`bekliyor → onaylandı → hazırlanıyor → hazır → teslim`)
 *    ve yalnız o akış geçerlidir.
 * 2. Yan dallar SAHİBİ OLAN dallardır: iptal misafirin, ret garsonun sözüdür.
 * 3. Mutfak yalnız onaylanmışı görür — bekleyeni GÖRMEZ.
 */
final class OrderStatusMachineTest extends TestCase
{
    public function test_the_drawn_flow_is_walkable_end_to_end_by_staff(): void
    {
        $chain = [
            [OrderStatus::Pending, OrderStatus::Confirmed],
            [OrderStatus::Confirmed, OrderStatus::Preparing],
            [OrderStatus::Preparing, OrderStatus::Ready],
            [OrderStatus::Ready, OrderStatus::Delivered],
        ];

        foreach ($chain as [$from, $to]) {
            self::assertTrue(
                $from->canTransitionTo($to, OrderActor::Staff),
                "`docs/115` §2: {$from->value} → {$to->value} çizilen akışın parçası."
            );
        }
    }

    public function test_a_step_cannot_be_skipped(): void
    {
        // Şişe suyun hazırlanması gerekmez diye onaydan doğrudan "hazır"a
        // atlamak CAZİP ve UYDURMADIR: sahibin tarif ettiği akışta böyle bir
        // dal yok. Ürün, sahibin çizmediği bir yolu kendi kendine açmaz.
        self::assertFalse(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Ready, OrderActor::Staff));
        self::assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Preparing, OrderActor::Staff));
        self::assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Delivered, OrderActor::Staff));
    }

    public function test_the_guest_cancels_only_before_confirmation(): void
    {
        self::assertTrue(
            OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled, OrderActor::Guest),
            '`docs/115` M5: onaylanmadan önce misafir iptal eder.'
        );

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready] as $after) {
            self::assertFalse(
                $after->canTransitionTo(OrderStatus::Cancelled, OrderActor::Guest),
                "M5: {$after->value} durumundan sonra iptal YOK — mutfak işe başlamış olabilir."
            );
        }
    }

    public function test_reject_belongs_to_staff_and_cancel_belongs_to_the_guest(): void
    {
        // İki kelime iki farklı olayı anlatır ve karıştırılamaz: reddetmek
        // garsonun kararıdır ve sebebi vardır; iptal misafirin vazgeçmesidir.
        self::assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled, OrderActor::Staff));
        self::assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Rejected, OrderActor::Guest));
        self::assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Rejected, OrderActor::Staff));
    }

    public function test_the_guest_can_never_move_the_order_forward(): void
    {
        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered] as $forward) {
            self::assertFalse(
                OrderStatus::Pending->canTransitionTo($forward, OrderActor::Guest),
                "Misafir kendi talebini iş hâline getiremez ({$forward->value})."
            );
        }
    }

    public function test_final_states_are_dead_ends(): void
    {
        foreach ([OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Rejected] as $final) {
            self::assertTrue($final->isFinal(), "{$final->value} bir son durumdur.");

            foreach (OrderStatus::cases() as $target) {
                foreach (OrderActor::cases() as $actor) {
                    self::assertFalse(
                        $final->canTransitionTo($target, $actor),
                        "{$final->value} → {$target->value} olamaz: kapanmış bir sipariş yeniden açılmaz."
                    );
                }
            }
        }
    }

    public function test_a_status_never_transitions_to_itself(): void
    {
        // İKİNCİ ONAY DENEMESİ SESSİZCE GEÇMEZ (`docs/115` G5). Kendine geçiş
        // "başarılı" sayılsaydı, iki garson aynı siparişi onayladığında
        // ikisi de onayladığını sanırdı.
        foreach (OrderStatus::cases() as $status) {
            foreach (OrderActor::cases() as $actor) {
                self::assertFalse(
                    $status->canTransitionTo($status, $actor),
                    "{$status->value} → {$status->value} bir geçiş değildir."
                );
            }
        }
    }

    public function test_the_kitchen_sees_confirmed_work_and_never_a_pending_request(): void
    {
        self::assertFalse(
            OrderStatus::Pending->isVisibleToKitchen(),
            '`docs/115` K1: bekleyen sipariş mutfağa HİÇ görünmez — aşçı onaylanmamış işe başlardı.'
        );

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready] as $visible) {
            self::assertTrue($visible->isVisibleToKitchen(), "{$visible->value} mutfak monitöründe görünür.");
        }

        foreach ([OrderStatus::Cancelled, OrderStatus::Rejected] as $gone) {
            self::assertFalse($gone->isVisibleToKitchen(), "{$gone->value} mutfakta iş değildir.");
        }
    }

    public function test_open_orders_are_the_ones_the_table_still_waits_for(): void
    {
        // "Masa başına açık sipariş sınırı" bu tanımın üstünde durur: kapanmış
        // bir sipariş masayı kilitlemez, yoksa akşam boyunca yemek yiyen bir
        // masa ikinci kez sipariş veremezdi.
        foreach ([OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready] as $open) {
            self::assertTrue($open->isOpen(), "{$open->value} açık bir siparistir.");
        }

        foreach ([OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Rejected] as $closed) {
            self::assertFalse($closed->isOpen(), "{$closed->value} kapanmıştır.");
        }
    }

    public function test_open_keys_cover_exactly_the_open_states(): void
    {
        $expected = [
            OrderStatus::Pending->value,
            OrderStatus::Confirmed->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
        ];

        self::assertSame($expected, OrderStatus::openKeys());
    }

    public function test_every_status_value_fits_the_column(): void
    {
        // POSTGRESQL TUZAĞI: SQLite `varchar(n)` sınırını hiç uygulamaz, PG
        // uygular. Sütun genişliği burada donuyor; yeni bir durum eklendiğinde
        // bu test göçten önce kırılır.
        foreach (OrderStatus::cases() as $status) {
            self::assertLessThanOrEqual(
                OrderStatus::MAX_VALUE_LENGTH,
                strlen($status->value),
                "{$status->value} `orders.status` sütununa sığmıyor."
            );
        }
    }
}
