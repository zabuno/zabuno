import { t } from '../../../i18n/workspace';
import { ActionMenu } from '../../catalog/overlays/compound/ActionMenu';

export type GlobalCreateTarget = {
    key: string;
    labelKey: string;
    /** Gidilecek bölüm; `locations/new` gibi bölüm içi bir yol olabilir. */
    destination: string;
    /**
     * Bu hedef ŞU AN oluşturulabilir mi.
     *
     * Ön koşulu olmayan bir hedefi listelemek, kullanıcıyı çıkışsız bir
     * ekrana götürmek olurdu: menü oluşturmak için önce bir şube gerekir ve
     * "Menü" maddesi şubesiz bir çalışma alanında yalnız bir hayal kırıklığı
     * üretir.
     */
    available: boolean;
};

export type GlobalCreateMenuProps = {
    targets: GlobalCreateTarget[];
    onNavigate: (destination: string) => void;
};

/**
 * Global oluştur — `docs/50` §10, `docs/64`.
 *
 * Üç kural bu menüyü tanımlıyor:
 *
 * 1. **Yalnız GERÇEK hedefler.** Her madde, o şeyin gerçekten oluşturulduğu
 *    ekrana götürür. Var olmayan bir akışa bağlantı koymak, olmayan bir
 *    yetenek vaat etmektir.
 * 2. **Ön koşulu olmayan madde listelenmez.** Şubesiz bir çalışma alanında
 *    "Menü" maddesi çıkışsız bir ekrana götürürdü.
 * 3. **Sayfanın kendi birincil eylemini KOPYALAMAZ, yalnız alternatif bir
 *    yol açar.** Menü ekranında "Ürün ekle" zaten görünürken buraya ikinci
 *    bir kopya koymak, aynı işi iki yerde arattırır.
 *
 * Hiçbir hedef uygun değilse menü hiç çizilmez: boş bir "Oluştur" düğmesi,
 * tıklandığında hiçbir şey sunmayan bir vaattir.
 */
export function GlobalCreateMenu({ targets, onNavigate }: GlobalCreateMenuProps) {
    const available = targets.filter((target) => target.available);

    if (available.length === 0) {
        return null;
    }

    return (
        <ActionMenu
            label={t('workspace.create.menu.label')}
            triggerContent={t('workspace.create.menu.label')}
            items={available.map((target) => ({
                key: target.key,
                label: t(target.labelKey as Parameters<typeof t>[0]),
                onSelect: () => onNavigate(target.destination),
            }))}
        />
    );
}
