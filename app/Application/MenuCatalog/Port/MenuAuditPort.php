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
 * OKUMA FF-163'TE GELDİ. FF-154 bu portu bilerek yalnız yazıcı olarak
 * bıraktı: o an izi okuyan bir ekran yoktu ve çağıranı olmayan bir metot
 * ölü koddur. Artık çağıranı var (`ListMenuAuditsController`), yani metot
 * da var — sırayı tersine çevirmedik.
 *
 * YAZMA ASLA ASIL İŞLEMİ DÜŞÜRMEZ: `record()` `void` döner ve istisna
 * fırlatmaz (gerekçe uygulamada, `EloquentMenuAudit`). Sahibin fiyat
 * değişikliği, denetim satırı yazılamadı diye geri alınamaz.
 *
 * OKUMADA SİLME/DÜZELTME YOLU YOKTUR ve olmayacak: düzeltilebilen bir
 * denetim izi denetim izi değildir. Tabloda `updated_at` sütunu bile yok
 * (`MenuAuditTrailTest::test_the_trail_has_no_update_column`).
 */
interface MenuAuditPort
{
    public function record(MenuAuditEntry $entry): void;

    /**
     * Çalışma alanının izinden BİR SAYFA — en yeni önce.
     *
     * SAYFA BOYUTU ÇAĞIRANIN KARARIDIR ama sınırsız değildir: uç onu
     * sabitler (`ListMenuAuditsController`). İz büyür — bir yıl işletilen
     * menüde binlerce satır olur — ve hepsini tek istekte göndermek, ekranı
     * açan sahibi bekletmenin en sessiz yoludur.
     *
     * `at` bir SAAT değil, mutlak bir ANDIR (ISO-8601, UTC); hangi duvar
     * saatiyle okunacağını `timeZone` söyler ve o, kaydın menüsünün bağlı
     * olduğu ŞUBENİN dilimidir (`docs/62`). Menü silinmişse şube de
     * bilinmez ve alan `null` kalır — uydurma bir şehir yazmak, ekrandaki
     * saati gerçekte olan andan ayırırdı.
     *
     * @return array{total:int, rows: list<array{
     *     id:int, action:string, subjectType:string, subjectId:int,
     *     subjectLabel:?string, before:?string, after:?string,
     *     actor:?string, at:?string, timeZone:?string
     * }>}
     */
    public function recent(int $workspaceId, int $page, int $perPage): array;
}
