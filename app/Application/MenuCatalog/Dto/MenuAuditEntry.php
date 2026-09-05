<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

use App\Domain\MenuCatalog\MenuAuditAction;
use App\Domain\MenuCatalog\MenuAuditSubject;
use App\Domain\Money\Money;
use InvalidArgumentException;

/**
 * Yazılmak üzere olan TEK bir denetim kaydı — FF-154.
 *
 * Neden DTO? Kaydın dokuz parçası var (kiracı, menü, konu türü, konu
 * kimliği, etiket, eylem, öncesi, sonrası, fail) ve bunları on üç ayrı
 * kontrolcüde dokuz konumsal argüman olarak taşımak, bir gün sessizce yer
 * değiştirecek iki `?string`'i yan yana koymak demekti — "öncesi" ile
 * "sonrası"nın karışması, izi ters yönde okutan ve hiçbir testin
 * yakalamayacağı bir hatadır.
 *
 * Üç fabrika, üç düzeyi kendi diliyle kurar; hangi konu türünün hangi
 * kimlikle geldiği çağrı yerinde okunur.
 *
 * DEĞER BİÇİMLERİ BURADA SABİTLENİR. Fiyat "380.00 TRY", görünürlük
 * "visible"/"hidden", alerjen listesi alfabetik ve virgülle ayrılmış olarak
 * yazılır. Biçimi çağrı yerlerine bıraksaydık aynı olay iki kontrolcüde iki
 * farklı biçimde kaydedilir ve iki farklı şeymiş gibi okunurdu.
 */
final class MenuAuditEntry
{
    private function __construct(
        public readonly int $workspaceId,
        public readonly ?int $menuId,
        public readonly MenuAuditSubject $subject,
        public readonly int $subjectId,
        public readonly ?string $subjectLabel,
        public readonly MenuAuditAction $action,
        public readonly ?string $before,
        public readonly ?string $after,
        public readonly ?int $actorUserId,
    ) {}

    public static function forMenu(
        int $workspaceId,
        int $menuId,
        ?string $label,
        MenuAuditAction $action,
        ?string $before,
        ?string $after,
        ?int $actorUserId,
    ): self {
        return new self($workspaceId, $menuId, MenuAuditSubject::Menu, $menuId, $label, $action, $before, $after, $actorUserId);
    }

    public static function forCategory(
        int $workspaceId,
        ?int $menuId,
        int $categoryId,
        ?string $label,
        MenuAuditAction $action,
        ?string $before,
        ?string $after,
        ?int $actorUserId,
    ): self {
        return new self($workspaceId, $menuId, MenuAuditSubject::Category, $categoryId, $label, $action, $before, $after, $actorUserId);
    }

    public static function forItem(
        int $workspaceId,
        ?int $menuId,
        int $menuItemId,
        ?string $label,
        MenuAuditAction $action,
        ?string $before,
        ?string $after,
        ?int $actorUserId,
    ): self {
        return new self($workspaceId, $menuId, MenuAuditSubject::MenuItem, $menuItemId, $label, $action, $before, $after, $actorUserId);
    }

    /**
     * Fiyatın insan-okur hâli: "380.00 TRY".
     *
     * Kuruş cinsinden yazsaydık ("38000 TRY") kayıt teknik olarak doğru,
     * pratikte okunmaz olurdu — ve kayıt zaten sahibin okuması için var.
     * Ondalık basamak sayısı para biriminin kendisinden gelir; JPY'de
     * ".00" uydurmak yanlış bir fiyat gösterirdi.
     *
     * BİÇİMLENDİRME ASLA İSTEĞİ DÜŞÜRMEZ. `Money` tanımadığı bir para
     * birimini reddeder ve bu doğru davranıştır — ama burada, YAZILMIŞ bir
     * satırı sonradan OKURKEN çalışıyoruz. Veritabanında bir gün beklenmedik
     * bir kod bulunursa, sahibin fiyat değişikliğinin bu yüzden geri
     * alınması "denetim izi asıl işlemi bozmaz" kuralını tam da denetim
     * izinin kendi sınıfında delerdi. Böyle bir durumda kayıt ham hâliyle
     * yazılır: okunması zor, ama kaybolmuş değil.
     */
    public static function price(int $minorAmount, string $currencyCode): string
    {
        try {
            $decimal = Money::fromMinorAmount($minorAmount, $currencyCode)->toDecimalString();
        } catch (InvalidArgumentException) {
            $decimal = (string) $minorAmount;
        }

        return $decimal.' '.$currencyCode;
    }

    /**
     * Görünürlüğün iki hâli.
     *
     * `true`/`false` yerine kelime yazılır: iz bir gün ekrana çıktığında
     * "1 → 0" satırını kimse okuyamaz.
     */
    public static function visibility(bool $isVisible): string
    {
        return $isVisible ? 'visible' : 'hidden';
    }

    /**
     * Alerjen listesi, ALFABETİK ve virgüllü.
     *
     * Sıralama şart: kaynak sıra veritabanı sırasıdır ve aynı küme iki
     * farklı sırada gelirse "alerjen değişti" diye yanlış bir kayıt doğar.
     *
     * @param  list<string>  $allergens
     */
    public static function allergens(array $allergens): string
    {
        sort($allergens);

        return implode(', ', $allergens);
    }
}
