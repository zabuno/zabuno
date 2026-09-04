import { t } from '../../../i18n/workspace';
import { SidebarMenu } from '../../catalog/overlays/compound/SidebarMenu';

export type WorkspaceSwitcherOption = {
    id: number;
    name: string;
};

export type WorkspaceSwitcherTriggerProps = {
    workspaceName?: string;
    /** Kullanıcının üyesi olduğu çalışma alanları; menü onlardan kurulur. */
    workspaces?: WorkspaceSwitcherOption[];
    currentWorkspaceId?: number;
    onSelectWorkspace?: (workspaceId: number) => void;
};

/**
 * Kenar çubuğunun ÜSTÜNDEKİ çalışma alanı seçici — `docs/50` §6.
 *
 * Bu bir gezinti maddesi değil, gezintinin üstündeki BAĞLAMDIR: "hangi
 * restorandayım" sorusunun cevabı listenin içinde aranmaz, listenin başında
 * durur.
 *
 * 2026-09-04'e kadar bu düğme AYRI BİR SAYFAYA götürüyordu: kabuk tamamen
 * kayboluyor, kullanıcı boş bir listeye düşüyor ve seçtikten sonra geri
 * geliyordu. Sahibin kararıyla o sayfa kaldırıldı — çalışma alanı değiştirmek
 * bir yolculuk değil, bir seçimdir ve yerinde yapılır.
 *
 * Tek çalışma alanı olsa bile menü AÇILIR: panel o tek alanı işaretli
 * gösterir ve "başka bir yerim var mı?" sorusunu cevaplar. Kontrolü yalnız
 * ikinci alan eklendiğinde belirtmek, kullanıcıya ürünün kurallarını
 * ezberletmek olurdu.
 */
export function WorkspaceSwitcherTrigger({
    workspaceName,
    workspaces = [],
    currentWorkspaceId,
    onSelectWorkspace,
}: WorkspaceSwitcherTriggerProps) {
    if (workspaceName === undefined) {
        return null;
    }

    /*
        GÖRÜNÜM (sahibin isteği, 2026-09-04: "atıl kalmış, stilize
        edilmemiş").

        Eski hâl iki satırdı: ad, altında büyük harfli gri bir "SWITCH
        WORKSPACE". O satır ne bilgi taşıyordu ne de bir eylemdi — kutunun
        zaten yaptığı işi ikinci kez yazıyordu ve kutuyu iki kat
        uzatıyordu. Yerine, çalışma alanının BAŞ HARFİ bir karo olarak
        geldi: göz uzun adı okumadan önce onu tanır, ve iki restoran arasında
        gidip gelen biri için renkten önce gelen ayırt edici işaret budur.
    */
    const label = (
        <span className="flex min-w-0 items-center gap-[var(--space-2)]">
            <span
                aria-hidden="true"
                className="flex h-[1.75rem] w-[1.75rem] shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-[var(--color-surface-active)] text-meta font-semibold text-fg"
            >
                {workspaceName.slice(0, 1).toLocaleUpperCase()}
            </span>
            <span className="truncate text-body font-semibold text-fg">{workspaceName}</span>
        </span>
    );

    return (
        <SidebarMenu
            className="mb-[var(--space-5)]"
            /*
                Erişilebilir ad HEM bağlamı HEM eylemi taşır: yalnız "çalışma
                alanı değiştir" deseydik, ekran okuyucu kullanan biri hangi
                restoranda olduğunu düğmeden hiç duymazdı — oysa bu kutunun
                asıl işi o soruyu cevaplamak.
            */
            label={`${workspaceName} — ${t('workspace.current.switch')}`}
            placement="down"
            triggerContent={label}
            items={workspaces.map((workspace) => ({
                key: String(workspace.id),
                label: workspace.name,
                selected: workspace.id === currentWorkspaceId,
                onSelect: () => onSelectWorkspace?.(workspace.id),
            }))}
        />
    );
}
