import { useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';

type WorkspaceIdentityRegionProps = {
    /** Kabuğun açık olduğu çalışma alanı; sunucunun cevabıyla eşleşmeli. */
    workspaceId: number;
};

type WorkspaceContext = { id: number; name: string; slug: string };

/**
 * Ayarlar > Çalışma alanı — kanonik kaynak (`panel.dc.html` > "Ayarlar" >
 * ikinci sekme).
 *
 * Kaynakta bu sekme çalışma alanının ADINI ve PANEL ADRESİNİ salt-okunur
 * gösterir, adresin altına da sebebini yazar: "Değiştirilemez — ekip
 * bağlantıları buna bağlı."
 *
 * NEDEN VAR: depoda bu sekmenin yerinde kişisel ad/şifre formu duruyordu ve
 * çalışma alanının kendisi ürünün hiçbir ekranında yazılı değildi. İki
 * restoranı olan bir sahip "şu an hangi paneldeyim?" sorusunu ancak tarayıcı
 * adres çubuğunu okuyarak cevaplayabiliyordu.
 *
 * VERİ UYDURULMAZ (docs/109 §4 madde 3). Kaynağın aynı sekmedeki üç bloğu
 * BİLEREK ÇİZİLMEDİ, çünkü arkalarında ne uç nokta ne veri var:
 *
 * - **Misafir menüsü dilleri.** Ürün tek dilli bir menü yayınlar; markanın
 *   `locale` alanı ana dili seçer. Çoklu dil ve "eksik çeviri misafire
 *   gösterilmez" kuralı bir çeviri deposu ister, o depo yok. Çizilseydi
 *   sahip dil açar, misafir hiçbir değişiklik görmezdi.
 * - **Özel alan adı.** DNS doğrulaması, sertifika ve "doğrulanınca QR adresi
 *   otomatik buna döner" akışı gerektirir. Hiçbiri yok; bir metin kutusu
 *   koymak, ödeme yapmış bir sahibi haftalarca bekletirdi.
 * - **Tehlikeli bölge (dışa aktar / çalışma alanını kapat).** Geri
 *   döndürülemez etki taşır ve sahibin kararı olmadan çizilmez.
 *
 * Bu üçü doğduğunda buraya gelir; bugünün ekranı yalnız GERÇEK olanı söyler.
 */
export function WorkspaceIdentityRegion({ workspaceId }: WorkspaceIdentityRegionProps) {
    const [context, setContext] = useState<WorkspaceContext | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch('/api/workspace-context', buildAuthRequestInit());

                if (cancelled) return;

                if (!response.ok) {
                    setFailed(true);

                    return;
                }

                const body = (await response.json()) as WorkspaceContext | null;

                if (cancelled) return;

                if (body === null || typeof body.slug !== 'string') {
                    setFailed(true);

                    return;
                }

                setContext(body);
            } catch {
                if (!cancelled) setFailed(true);
            }
        })();

        return () => {
            cancelled = true;
        };
        // `workspaceId` bağımlılıktır: kabuk başka bir çalışma alanına
        // geçtiğinde bu kart eski adı göstermeye devam etmemeli.
    }, [workspaceId]);

    const fieldClass =
        'w-full min-h-[var(--control-height)] rounded-md border border-border bg-[var(--color-surface-subtle)] px-[var(--space-3)] py-[var(--space-2)] text-body text-fg-secondary';
    const labelClass = 'block text-body font-medium text-fg-secondary';

    if (failed) {
        return (
            <section
                aria-label={t('workspace.settings.workspace.region')}
                className="flex flex-col gap-[var(--space-2)]"
            >
                {/*
                    Sunucu susarsa bölüm SESSİZCE BOŞ KALMAZ: boş bir kart,
                    kullanıcıya "çalışma alanım silinmiş" dedirtir.
                */}
                <p role="alert" className="text-body text-fg-danger">
                    {t('workspace.settings.workspace.error')}
                </p>
            </section>
        );
    }

    if (context === null) {
        return (
            <section
                aria-label={t('workspace.settings.workspace.region')}
                className="flex flex-col gap-[var(--space-2)]"
            >
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.settings.workspace.loading')}
                </p>
            </section>
        );
    }

    /*
        Panel adresi TÜRETİLMEZ, OKUNUR: kabuk bu çalışma alanını
        `/app/<slug>` altında açar ve gösterilen adres tam olarak odur.
        Elle yazılmış bir alan adı, sunucu değişince yalan söylerdi.
    */
    const panelAddress = `${window.location.origin}/app/${context.slug}`;

    return (
        <section
            aria-label={t('workspace.settings.workspace.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <div className="flex flex-col gap-[var(--space-2)]">
                <label className={labelClass} htmlFor="workspace-identity-name">
                    {t('workspace.settings.workspace.name')}
                </label>
                <input
                    id="workspace-identity-name"
                    name="workspace-identity-name"
                    type="text"
                    readOnly
                    className={fieldClass}
                    value={context.name}
                />
            </div>

            <div className="flex flex-col gap-[var(--space-2)]">
                <label className={labelClass} htmlFor="workspace-identity-address">
                    {t('workspace.settings.workspace.address')}
                </label>
                <input
                    id="workspace-identity-address"
                    name="workspace-identity-address"
                    type="text"
                    readOnly
                    className={fieldClass}
                    value={panelAddress}
                />
                {/*
                    Sebebi yazılmayan bir kilit, kullanıcıya arıza gibi
                    görünür: "adresimi neden düzenleyemiyorum?" sorusu
                    destek talebine dönüşürdü.
                */}
                <p className="text-body text-fg-secondary">
                    {t('workspace.settings.workspace.address.help')}
                </p>
            </div>
        </section>
    );
}

export default WorkspaceIdentityRegion;
