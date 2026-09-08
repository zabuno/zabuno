<?php

declare(strict_types=1);

namespace App\Application\Support\Port;

use App\Application\Support\Dto\SupportAccessSessionRow;

/**
 * Kiracı olarak bakma oturumları — `docs/122` Y7, `docs/133`.
 *
 * OTURUM SUNUCUDA YAŞAR. Bu port bir çerezi ya da istemci durumunu değil,
 * bir veritabanı satırını okur; `activeFor` her istekte yeniden sorulur ve
 * süre dolduğunda cevabı kendiliğinden `null` olur. Tarayıcı tarafında
 * saklanan bir oturum, süresi dolduğunda kimsenin zorlayamayacağı bir söz
 * olurdu.
 *
 * UZATMA FİİLİ YOKTUR ve olmayacak. Arayüzde `extend`/`renew` benzeri bir
 * yöntemin BULUNMAMASI, `docs/122` §5'in "süreli olur" şartının tip
 * düzeyindeki karşılığıdır: uzatmayı kimseye vermemek için önce uzatmayı
 * bir yetenek olarak adlandırmamak gerekir.
 */
interface SupportAccessPort
{
    /**
     * Bu kullanıcının ŞU AN açık oturumu — yoksa null.
     *
     * Süresi dolmuş bir satır açık sayılmaz: bitiş anı geçtiyse oturum
     * bitmiştir, kimse "çıkış" yapmamış olsa bile.
     */
    public function activeFor(int $userId): ?SupportAccessSessionRow;

    /**
     * Yeni oturum açar ve kaydı yazar.
     *
     * Sebep zorunludur ve boş geçilemez; doğrulama Delivery katmanında
     * yapılır, burada sözleşme olarak durur.
     */
    public function open(int $workspaceId, int $actorUserId, string $reason, int $minutes): SupportAccessSessionRow;

    /**
     * Oturumu erken bitirir. Zaten bitmiş bir oturum için false döner —
     * ikinci kez bitirmek bir olay değildir.
     */
    public function end(int $sessionId, int $actorUserId): bool;

    /**
     * Bir çalışma alanının son bakış kayıtları, en yeni üstte. Kiracının
     * kendi panelinde de, süperadmin ekranında da AYNI liste okunur.
     *
     * @return list<SupportAccessSessionRow>
     */
    public function forWorkspace(int $workspaceId, int $limit = 20): array;

    /**
     * Gönderim sonucunu satıra yazar (`docs/93`): `null` çıktı demektir,
     * bir dize sebebi taşır, `false` hiç denenmedi demektir — ve
     * denenmemişe damga basılmaz.
     */
    public function recordOwnerNotification(int $sessionId, string|false|null $failure): void;
}
