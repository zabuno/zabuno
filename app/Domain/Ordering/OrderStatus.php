<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

/**
 * SİPARİŞ DURUM MAKİNESİ — `docs/115` §2 (FF-176 / S1).
 *
 * ```
 *   taslak (cihazda, sunucuya hiç gitmez)
 *      │  misafir "siparişi gönder" der
 *      ▼
 *   bekliyor ────────────► iptal (misafir, yalnız onaydan önce)
 *      │  garson/yönetici onaylar
 *      ▼
 *   onaylandı ──────────► reddedildi (garson, sebebiyle)
 *      │  mutfak monitörüne düşer
 *      ▼
 *   hazırlanıyor ──► hazır ──► teslim edildi
 * ```
 *
 * **"Bekliyor" ile "onaylandı" arasındaki fark bu akışın kemiğidir.**
 * Misafirin gönderdiği bir TALEP, garsonun onayladığı bir İŞtir. Talebi
 * doğrudan mutfağa düşürmek, masada oturmayan birinin mutfağa iş
 * açabilmesi demektir — ve bu ürünün tek insani kapısı garsonun gözüdür.
 *
 * KURAL TEK YERDE. Bu enum, geçişin tek karar merciidir. İkinci bir yerde
 * (denetleyicide, ekranda, sorguda) "şu durumdaysa şunu yapabilir" yazmak,
 * bir gün iki cevabın ayrışması demektir; ve ayrıştıkları ancak yanlış bir
 * siparişin mutfağa düşmesiyle anlaşılır.
 *
 * ADIM ATLANMAZ. Şişe suyun hazırlanması gerekmediği için onaydan doğrudan
 * "hazır"a atlamak cazip görünür; sahibin tarif ettiği akışta böyle bir dal
 * yoktur. Ürün, sahibin çizmediği bir yolu kendi başına açmaz — gerekiyorsa
 * ölçülür ve eklenir.
 *
 * KENDİNE GEÇİŞ BİR GEÇİŞ DEĞİLDİR (`docs/115` G5). İkinci onay denemesi
 * "başarılı" sayılsaydı, iki garson aynı siparişi onayladığında ikisi de
 * onayı kendisinin verdiğini sanırdı. Burada `false` döner; çağıran katman
 * durumu söyleyerek reddeder.
 */
enum OrderStatus: string
{
    /**
     * `orders.status` sütununun genişliği.
     *
     * PostgreSQL `varchar(n)` sınırını UYGULAR, SQLite hiç uygulamaz — yani
     * sığmayan bir değer yerelde geçer, dağıtım motorunda isteği reddeder.
     * Sabit burada durur ki yeni bir durum eklendiğinde kural testi göçten
     * ÖNCE kırılsın.
     */
    public const MAX_VALUE_LENGTH = 20;

    /** Misafir gönderdi; garsonun gözü henüz görmedi. */
    case Pending = 'pending';

    /** Garson onayladı; artık mutfağın işi. */
    case Confirmed = 'confirmed';

    /** Ocak başladı. */
    case Preparing = 'preparing';

    /** Servise hazır. */
    case Ready = 'ready';

    /** Masaya gitti; akış bitti. */
    case Delivered = 'delivered';

    /** Misafir vazgeçti — yalnız onaydan ÖNCE. */
    case Cancelled = 'cancelled';

    /** Garson reddetti; sebebi misafirin ekranında görünür. */
    case Rejected = 'rejected';

    /**
     * Masanın hâlâ beklediği siparişler.
     *
     * "Masa başına açık sipariş sınırı" bu tanımın üstünde durur: kapanmış
     * bir sipariş masayı kilitlemez. Aksi hâlde akşam boyunca yemek yiyen
     * bir masa ikinci turu hiç veremezdi.
     */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Pending, self::Confirmed, self::Preparing, self::Ready => true,
            self::Delivered, self::Cancelled, self::Rejected => false,
        };
    }

    /** Kapanmış sipariş yeniden açılmaz. */
    public function isFinal(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * Mutfak monitörü YALNIZ onaylanmışı ve sonrasını görür
     * (`docs/115` K1).
     *
     * Bekleyen sipariş mutfağa hiç görünmez; görünseydi aşçı onaylanmamış
     * bir işi hazırlamaya başlardı ve garsonun gözü diye bir kapı kalmazdı.
     */
    public function isVisibleToKitchen(): bool
    {
        return match ($this) {
            self::Confirmed, self::Preparing, self::Ready => true,
            default => false,
        };
    }

    /**
     * Bu durumdan, bu elle gidilebilecek durumlar.
     *
     * @return list<self>
     */
    public function allowedNext(OrderActor $actor): array
    {
        return match ($this) {
            self::Pending => match ($actor) {
                // Misafirin elindeki tek düğme vazgeçmektir: kendi talebini
                // iş hâline getiremez.
                OrderActor::Guest => [self::Cancelled],
                OrderActor::Staff => [self::Confirmed, self::Rejected],
            },
            // Onaydan SONRA misafirin iptali YOKTUR (`docs/115` M5): mutfak
            // işe başlamış olabilir ve o maliyet gerçektir.
            self::Confirmed => $actor === OrderActor::Staff ? [self::Preparing] : [],
            self::Preparing => $actor === OrderActor::Staff ? [self::Ready] : [],
            self::Ready => $actor === OrderActor::Staff ? [self::Delivered] : [],
            self::Delivered, self::Cancelled, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target, OrderActor $actor): bool
    {
        return in_array($target, $this->allowedNext($actor), true);
    }

    /**
     * Açık durumların anahtarları — sorgu katmanının okuduğu liste.
     *
     * Altyapı katmanı bu listeyi ELLE YAZMAZ: `WHERE status IN (...)`
     * içinde tekrar edilen bir liste, yeni bir durum eklendiği gün sessizce
     * eksik kalır ve masa sınırı yanlış sayar.
     *
     * @return list<string>
     */
    public static function openKeys(): array
    {
        $keys = [];

        foreach (self::cases() as $case) {
            if ($case->isOpen()) {
                $keys[] = $case->value;
            }
        }

        return $keys;
    }
}
