<?php

declare(strict_types=1);

namespace App\Application\Workspace\Port;

/**
 * Çalışma alanının denetim izi — "bunu kim, ne zaman yaptı?" (FF-132).
 *
 * Port, çünkü izin NEREDEN toplandığı bir altyapı kararıdır: bugün iki ayrı
 * tablodan okunuyor, yarın tek bir olay günlüğüne taşınabilir. Uygulama
 * tarafının bilmesi gereken tek şey, geriye tek bir zaman çizgisi geldiği.
 *
 * Bu port YENİ KAYIT TUTMAZ. Var olan kayıtları birleştirir; uydurmaz,
 * tamamlamaz, boşluğu doldurmaz. Kaydı olmayan bir olay burada da yoktur ve
 * bu dürüsttür: sahibin "her şey burada" sanması, eksik bir izden daha
 * tehlikelidir.
 */
interface WorkspaceAuditTrailPort
{
    /**
     * Bir çalışma alanının son olayları — en yeni önce.
     *
     * @return array<int, array{source:string, action:string, subject:?string, actor:?string, at:?string}>
     */
    public function recent(int $workspaceId, int $limit = 100): array;
}
