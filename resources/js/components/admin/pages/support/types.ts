/**
 * Destek masasının sunucudan okuduğu şekiller — `docs/122` Y7, `docs/133`.
 *
 * Tipler ayrı bir dosyada, çünkü aynı şekli üç dosya okuyor: sunum bileşeni,
 * getirme kabuğu ve hikâyeler. Üçünde ayrı ayrı tanımlansaydı, sunucu bir
 * alan eklediğinde ikisi güncellenir üçüncüsü sessizce ayrışırdı.
 */

export type SupportAccessSession = {
    id: number;
    workspaceId: number;
    actor: string | null;
    reason: string;
    startedAt: string;
    expiresAt: string;
    endedAt: string | null;
    active: boolean;
};

export type SupportQrCode = {
    id: number;
    /** Misafirin gördüğü adres — karekod zaten masada, adres zaten açık. */
    guestUrl: string;
    state: string;
    destinationType: string | null;
    menuId: number | null;
    menuName: string | null;
    publishedVersion: number | null;
    publishedAt: string | null;
};

export type SupportLocation = {
    id: number;
    displayName: string;
    acceptsOrders: boolean;
    menuCount: number;
    qrCodes: SupportQrCode[];
};

export type SupportRequestRow = {
    reference: string;
    subject: string;
    status: string;
    receivedAt: string;
    firstResponseAt: string | null;
};

/** Her bulgu bir SORGUNUN sonucudur; hiçbiri tahmin ya da öneri taşımaz. */
export type SupportFinding = {
    code: string;
    locationId: number | null;
};

export type TenantSupportView = {
    workspace: { id: number; name: string; slug: string; state: string };
    subscription: { state: string; plan_name?: string; plan_code?: string; ends_at?: string };
    locations: SupportLocation[];
    supportRequests: SupportRequestRow[];
    accessHistory: SupportAccessSession[];
    findings: SupportFinding[];
};
