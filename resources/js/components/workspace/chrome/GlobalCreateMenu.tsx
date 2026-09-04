import type { ReactNode } from 'react';
import { ForkKnife, Plus, QrCode, Storefront, UserPlus } from '@phosphor-icons/react';
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
/*
    HEDEFİN İŞARETİ — AEP teslim paketi ("Restoran Paneli v2") menüyü
    ikonlu satırlar olarak çiziyor.

    Dört maddelik bir listede sözcükler birbirine benzer ("Menü", "Şube",
    "QR kod"); ikon, listeyi baştan okumadan hedefi bulmayı sağlayan tek
    işarettir ve kas hafızasını sözcükten değil BİÇİMDEN kurar.

    Eşleme BURADA duruyor, `GlobalCreateTarget` üzerinde bir alan olarak
    değil: ikon bir veri değil bir SUNUM kararıdır ve çağıran taraf
    (`WorkspaceApp`) hangi hedefin hangi resme benzediğini bilmek zorunda
    kalmamalı. Tanınmayan bir anahtar için ikon UYDURULMAZ — yanlış bir
    işaret, işaretsiz bir satırdan daha kötüdür.

    Boyut jetondan: `--control-indicator-size` menü satırındaki her işaretin
    tek ölçüsüdür.
*/
const ICON_SIZE = 'size-[var(--control-indicator-size)]';

const ICON_BY_TARGET: Record<string, ReactNode> = {
    location: <Storefront className={ICON_SIZE} />,
    menu: <ForkKnife className={ICON_SIZE} />,
    'qr-code': <QrCode className={ICON_SIZE} />,
    'team-member': <UserPlus className={ICON_SIZE} />,
};

export function GlobalCreateMenu({ targets, onNavigate }: GlobalCreateMenuProps) {
    const available = targets.filter((target) => target.available);

    if (available.length === 0) {
        return null;
    }

    return (
        <ActionMenu
            label={t('workspace.create.menu.label')}
            triggerContent={
                /*
                    İKON + SÖZ. Dar ekranda sözcük sığmadığında geriye
                    anlamı taşıyan bir işaret kalır. İkon `aria-hidden`:
                    düğmenin erişilebilir adı `label`'dan gelir ve
                    değişmez — ekran okuyucu "Create" duyar, bir "artı"
                    fazlası değil.
                */
                <span className="inline-flex items-center gap-[var(--space-2)]">
                    <Plus aria-hidden="true" className={ICON_SIZE} />
                    {t('workspace.create.menu.label')}
                </span>
            }
            items={available.map((target) => ({
                key: target.key,
                label: t(target.labelKey as Parameters<typeof t>[0]),
                icon: ICON_BY_TARGET[target.key],
                onSelect: () => onNavigate(target.destination),
            }))}
        />
    );
}
