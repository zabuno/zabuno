<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * YASAL SAKLAMA — kaynağın "Yönetişim" bölümündeki kilit
 * (`docs/reference/panel-v3/MedyaModulu.dc.html`).
 *
 * Toplu işlem portundan AYRIDIR ve bu bilinçli: kilidi soran ilk çağıran
 * toplu işlem değil, TEK DOSYA SİLMEDİR. Silme uçunun "toplu işlem
 * deposu"na bağımlı olması, okuyan herkese yanlış bir bağ gösterirdi —
 * oysa kilit tek bir dosyanın kendi hâlidir ve toplu işlem onu yalnız
 * okur.
 *
 * Kilit `deleted_at` gibi bir yaşam döngüsü durumu DEĞİLDİR: çöpteki bir
 * dosyanın da yasal saklaması olabilir ve o dosya süresi dolsa bile
 * kalıcı silinmemelidir.
 */
interface MediaLegalHoldPort
{
    /** Bu dosya yasal saklama altında mı? Yabancı kimlik `false` döner. */
    public function isHeld(int $workspaceId, int $assetId): bool;

    /**
     * Saklamayı koyar (`$reason` dolu) ya da kaldırır (`null`).
     * Döner: satır gerçekten değiştiyse `true`.
     */
    public function set(int $workspaceId, int $assetId, ?string $reason, ?int $actorUserId): bool;

    /**
     * Saklama altındaki dosyalar — yönetişim ekranının listesi.
     *
     * @return list<array{id:int, name:string, reason:string, at:?string}>
     */
    public function all(int $workspaceId): array;
}
