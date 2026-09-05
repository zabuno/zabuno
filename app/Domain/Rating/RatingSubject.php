<?php

declare(strict_types=1);

namespace App\Domain\Rating;

/**
 * NEYE PUAN VERİLDİ — `rating_signals.subject_type` (`docs/116` §1).
 *
 * ═══ NEDEN MENÜ SATIRI DEĞİL ÜRÜN ═══
 *
 * Misafir ekranda bir MENÜ SATIRI görür (`menu_items`) ama oy ÜRÜNE
 * (`products`) yazılır. Fark, sahibin menüsünü düzenlediği gün ortaya
 * çıkar: kahve satırı akşam menüsünden silinip kahvaltı menüsüne
 * eklendiğinde, ya da fiyatı değiştiği için satır yeniden kurulduğunda,
 * menü satırının kimliği değişir. Puanı oraya bağlasaydık, sahibin fiyat
 * güncellemesi ürünün bütün puanını sıfırlardı — ve hiç kimse sebebini
 * bulamazdı.
 *
 * Misafirin oyu bir tabak hakkındadır, bir satır hakkında değil.
 *
 * ═══ ÇOK BİÇİMLİ, ÇÜNKÜ YARIN ŞUBEYE DE PUAN VERİLECEK ═══
 *
 * Ayrı tablolar açmak (product_ratings, location_ratings) aynı algoritmayı
 * iki yerde çalıştırmak olurdu ve ikisi bir gün ayrışırdı.
 */
enum RatingSubject: string
{
    /**
     * `rating_signals.subject_type` / `rating_scores.subject_type`
     * sütununun genişliği.
     *
     * Göçle (`2026_09_07_000100`) aynı sayı; PostgreSQL `varchar(n)`'i
     * UYGULAR, SQLite hiç uygulamaz — yani sığmayan bir değer yerelde
     * sessizce geçer, dağıtım motorunda isteği reddeder.
     */
    public const MAX_VALUE_LENGTH = 32;

    /** Bir tabak. Bugün tek değer bu. */
    case Product = 'product';
}
