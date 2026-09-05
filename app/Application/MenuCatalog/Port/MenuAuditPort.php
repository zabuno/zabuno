<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Port;

use App\Application\MenuCatalog\Dto\MenuAuditEntry;

/**
 * Menü denetim izinin YAZICISI — FF-154.
 *
 * Port, çünkü izin NEREYE yazıldığı bir altyapı kararıdır; hangi olayın ize
 * değer olduğu ise uygulama kararıdır (`MenuAuditAction`). İkisi aynı sınıfta
 * dursaydı izi bir gün başka bir yere taşımak imkânsız olurdu — medya izinde
 * (`MediaAuditPort`) aynı gerekçe geçerli ve desen bilerek aynı.
 *
 * OKUMA YOK. `MediaAuditPort` bir `recent()` de taşır çünkü onu okuyan bir
 * ekran var. Bu paket EKRAN ÇİZMEZ: veri olmadan ekran çizmek bu deponun
 * yasağı, çağıranı olmayan bir okuma metodu ise ölü koddur. Ekran ayrı bir
 * pakette gelecek ve okuma yüzeyini o paket, kendi ihtiyacına göre ekler.
 *
 * YAZMA ASLA ASIL İŞLEMİ DÜŞÜRMEZ: metot `void` döner ve istisna fırlatmaz
 * (gerekçe uygulamada, `EloquentMenuAudit`). Sahibin fiyat değişikliği,
 * denetim satırı yazılamadı diye geri alınamaz.
 */
interface MenuAuditPort
{
    public function record(MenuAuditEntry $entry): void;
}
