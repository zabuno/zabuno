<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

enum Permission: string
{
    case WorkspaceView = 'workspace.view';
    case WorkspaceManage = 'workspace.manage';
    case MenuView = 'menu.view';
    case MenuManage = 'menu.manage';
    case MenuPublish = 'menu.publish';
    case QrView = 'qr.view';
    case QrCreate = 'qr.create';
    case QrDisable = 'qr.disable';
    case QrDesignManage = 'qr.design.manage';
    case AnalyticsView = 'analytics.view';
    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';
    case SecurityEvidenceView = 'security.evidence.view';
    /*
        Medya (`docs/49` Faz 7 madde 3). `media.manage` yükler/siler/geri
        alır/yeniden üretir; `media.download_original` AYRI bir izindir —
        sahibin kararıyla bugün her role verilir ("tamamen serbest"), ama
        bir gün daraltmak tek satır olsun diye ayrı yaşar.
    */
    case MediaManage = 'media.manage';
    case MediaDownloadOriginal = 'media.download_original';
}
