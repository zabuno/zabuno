<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Tenancy\MembershipRole;

/**
 * ROL × İZİN MATRİSİ — koddan TÜRETİLİR, elle yazılmaz (`docs/139`).
 *
 * ═══ NEDEN BİR SINIF, NEDEN BİR TABLO DEĞİL ═══
 *
 * Bu deponun izin gerçeği üç dosyada yaşıyor: `Permission` (hangi yetenekler
 * adlandırılmış), `RolePermissions` (hangi rol hangisini taşıyor),
 * `MembershipRole` (bir rol davet edilebilir mi, çıkarılabilir mi, sahiplik
 * devralabilir mi). Bir müşteriye gönderilecek matrisi elle yazmak, o üç
 * dosyanın DÖRDÜNCÜ bir kopyasını üretmek olurdu — ve dördüncü kopya, ilk
 * üçünden biri değiştiği gün sessizce yalan söylemeye başlardı.
 *
 * Bu sınıf o kopyayı yaratmaz; matrisi HER SEFERİNDE kaynaktan okur. Belge
 * ve ekran onun çıktısıdır, kardeşi değil.
 *
 * ═══ HİÇBİR YERDE ELLE LİSTE YOK ═══
 *
 * Ne izinlerin ne de rollerin listesi burada yazılı: ikisi de enum'un kendi
 * `cases()` çağrısından gelir. Yarın altıncı bir rol ya da yirmi dördüncü
 * bir izin doğduğunda bu dosyaya dokunmak GEREKMEZ — matris kendiliğinden
 * büyür, belge kapısı (`AuthorizationMatrixArtifactTest`) kırılır ve belge
 * yeniden üretilene kadar yeşile dönmez.
 */
final class RolePermissionMatrix
{
    /**
     * Matrisin tamamı.
     *
     * @return array{
     *     permissions: list<array{key: string, group: string, roles: list<string>}>,
     *     roles: list<array{
     *         key: string,
     *         granted: list<string>,
     *         denied: list<string>,
     *         invitable: bool,
     *         removable: bool,
     *         ownershipTransferable: bool,
     *     }>,
     * }
     */
    public static function build(): array
    {
        return [
            'permissions' => self::permissions(),
            'roles' => self::roles(),
        ];
    }

    /**
     * İzinler, kendi eksenleriyle birlikte.
     *
     * Eksen (`group`) izin anahtarının İLK PARÇASIDIR — `menu.publish` menü
     * ekseninde, `order.confirm` sipariş ekseninde. Elle bir gruplama
     * tablosu tutmak, anahtarla çelişebilecek ikinci bir gerçek yaratırdı;
     * anahtarın kendisi zaten o bilgiyi taşıyor.
     *
     * @return list<array{key: string, group: string, roles: list<string>}>
     */
    public static function permissions(): array
    {
        $rows = [];

        foreach (Permission::cases() as $permission) {
            $holders = [];

            foreach (self::roleOrder() as $role) {
                if (self::grants($role, $permission)) {
                    $holders[] = $role->value;
                }
            }

            $rows[] = [
                'key' => $permission->value,
                'group' => self::groupOf($permission),
                // Hangi roller taşıyor: satırı tek başına okunabilir yapar.
                // Hukuk birimi "bu yeteneğe kim erişebiliyor" diye sorar ve
                // cevabı sütunları saymadan görebilmelidir.
                'roles' => $holders,
            ];
        }

        return $rows;
    }

    /**
     * Roller, GENİŞTEN DARA.
     *
     * @return list<array{
     *     key: string,
     *     granted: list<string>,
     *     denied: list<string>,
     *     invitable: bool,
     *     removable: bool,
     *     ownershipTransferable: bool,
     * }>
     */
    public static function roles(): array
    {
        $invitable = self::values(MembershipRole::invitable());
        $removable = self::values(MembershipRole::removable());
        $transferable = self::values(MembershipRole::ownershipTransferable());

        $rows = [];

        foreach (self::roleOrder() as $role) {
            $granted = [];
            $denied = [];

            foreach (Permission::cases() as $permission) {
                if (self::grants($role, $permission)) {
                    $granted[] = $permission->value;

                    continue;
                }

                /*
                    YAPAMADIĞI DA YAZILIR ve BURADA ÜRETİLİR.

                    "Yapabildikleri" listesini yayımlayıp gerisini okuyucunun
                    çıkarmasına bırakmak, hukuk biriminin sorduğu soruyu
                    cevapsız bırakır: onlar "neyi yapamıyor" diye sorar. İki
                    liste aynı döngüden çıktığı için biri diğerinden asla
                    ayrışamaz — elle tutulan bir "yapamaz" listesi ayrışırdı.
                */
                $denied[] = $permission->value;
            }

            $rows[] = [
                'key' => $role->value,
                'granted' => $granted,
                'denied' => $denied,
                'invitable' => in_array($role->value, $invitable, true),
                'removable' => in_array($role->value, $removable, true),
                'ownershipTransferable' => in_array($role->value, $transferable, true),
            ];
        }

        return $rows;
    }

    /**
     * Rollerin okuma sırası: önce en çok izin taşıyan.
     *
     * Sıra bir KARAR değil bir ÖLÇÜMDÜR ve bilerek öyle: elle yazılmış bir
     * sıra, yeni bir rol doğduğunda onu listenin dışında bırakırdı. Eşitlik
     * hâlinde enum'un kendi bildirim sırası kazanır — sonuç her koşuda aynı
     * olsun diye (`usort` kararsızdır, bu yüzden sıralama anahtarı çifttir).
     *
     * @return list<MembershipRole>
     */
    private static function roleOrder(): array
    {
        $cases = MembershipRole::cases();
        $declared = [];

        foreach ($cases as $index => $role) {
            $declared[$role->value] = $index;
        }

        usort($cases, static function (MembershipRole $left, MembershipRole $right) use ($declared): int {
            $byBreadth = count(RolePermissions::for($right)) <=> count(RolePermissions::for($left));

            if ($byBreadth !== 0) {
                return $byBreadth;
            }

            return $declared[$left->value] <=> $declared[$right->value];
        });

        return array_values($cases);
    }

    private static function grants(MembershipRole $role, Permission $permission): bool
    {
        return in_array($permission, RolePermissions::for($role), true);
    }

    private static function groupOf(Permission $permission): string
    {
        $first = strstr($permission->value, '.', true);

        return $first === false ? $permission->value : $first;
    }

    /**
     * @param  list<MembershipRole>  $roles
     * @return list<string>
     */
    private static function values(array $roles): array
    {
        return array_map(static fn (MembershipRole $role): string => $role->value, $roles);
    }
}
