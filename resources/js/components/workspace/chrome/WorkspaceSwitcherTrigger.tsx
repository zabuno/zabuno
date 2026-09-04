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
    /*
        KARE MARKA RENGİNDE — AEP `DESIGN_SPEC` §1: "36px marka karesi
        (`brand` zemin, `action-fg` harf)".

        Önceki hâlde baş harf soluk gri bir karedeydi ve tam altındaki gezinti
        maddelerinin aktif zemini de aynı griydi: ekranın en üst KİMLİK
        işaretiyle "buradasın" işareti aynı renkti. İki restoranı olan bir
        işletmeci gün içinde ikisi arasında gidip gelir; kabuğu tek bakışta
        ayıran şey bu karedir, o yüzden markanın kendisini taşır.

        Sarı zeminde tek meşru yazı rengi `--color-action-fg` (ölçülmüş
        11.63:1). `text-fg` koyu temada beyaza döner ve sarının üstünde
        okunmazdı.
    */
    const label = (
        <span className="flex min-w-0 items-center gap-[var(--space-3)]">
            <span
                aria-hidden="true"
                data-slot="workspace-initial"
                className="flex h-[2.25rem] w-[2.25rem] shrink-0 items-center justify-center rounded-[var(--radius-lg)] bg-action text-body font-bold text-action-fg"
            >
                {workspaceName.slice(0, 1).toLocaleUpperCase()}
            </span>
            {/* Ağırlık ölçeği yalnız 400/500/700: 600, Roboto'da gerçek bir
                kesim değildir ve tarayıcı onu işletim sistemine göre farklı
                sentezler. */}
            <span className="truncate text-body font-bold text-fg">{workspaceName}</span>
        </span>
    );

    return (
        <SidebarMenu
            /*
                Seçiciyle gezinti arası 8px. 24px'lik eski aralık, kutuyu
                gezintinin başlığı değil AYRI BİR BÖLÜM gibi gösteriyordu;
                oysa bu kutu listenin üstündeki bağlamdır ve ona yakın durur.
            */
            className="mb-[var(--space-2)]"
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
