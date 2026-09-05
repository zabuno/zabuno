<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

/**
 * Bir durum geçişini KİMİN yaptığı — `docs/115` §2 (FF-176).
 *
 * Aktör, geçiş kuralının parçasıdır ve dışarıda bırakılamaz: aynı hedef
 * durum farklı ellerde farklı anlamlar taşır. "İptal" misafirin
 * vazgeçmesidir; "ret" garsonun kararıdır ve sebebi vardır. İkisini tek bir
 * "kapatıldı" durumuna indirmek en kolay kısayoldu ve misafirin ekranında
 * en pahalıya patlayanı: kendi vazgeçtiği bir siparişle restoranın
 * reddettiği bir sipariş aynı cümleyi gösterirdi.
 *
 * Aktör bir KİMLİK DEĞİLDİR, bir YETKİ EKSENİDİR: `Guest` anonimdir ve öyle
 * kalır. Panel tarafındaki gerçek yetki kararı (hangi rol onaylayabilir)
 * `docs/115` §4'e aittir ve ayrı bir pakette verilir; burada donan şey,
 * geçişin hangi TARAFA ait olduğudur.
 */
enum OrderActor: string
{
    /** Masadaki anonim misafir. */
    case Guest = 'guest';

    /** Restoran tarafı — garson, yönetici, mutfak. */
    case Staff = 'staff';
}
