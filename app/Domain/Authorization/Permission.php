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
}
