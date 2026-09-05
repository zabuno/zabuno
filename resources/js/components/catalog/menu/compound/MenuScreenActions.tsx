import type { ReactNode } from 'react';
import clsx from 'clsx';
import { Eye, FileCsv, Plus, Sparkle } from '@phosphor-icons/react';

/**
 * "Fotoğraftan aktar"ın ŞU ANKİ DURUMU.
 *
 * `blocked` bir hata değil, bir sebeptir: AI kapalı olabilir, ayın bütçesi
 * bitmiş olabilir, hiç sağlayıcı tanımlanmamış olabilir. Üçü üç ayrı
 * çözüme gider, bu yüzden düğmenin yerine tek bir "kullanılamıyor" metni
 * değil, sebebin kendisi yazılır (`docs/97` R9 / AIV-07).
 */
export type PhotoImportState =
    { kind: 'available'; label: string; onClick: () => void } | { kind: 'blocked'; reason: string };

/** Şeritteki bir MENÜ HAPI. */
export type MenuPill = {
    id: number;
    /** `menus.name`. */
    name: string;
    /**
     * Hapın altındaki tek satırlık ipucu: "07:00–11:00", "tüm gün",
     * "taslak", "kapalı". Sunucudan gelen gerçek veriden türetilir.
     */
    hint: string;
    isSelected: boolean;
    /** Şubenin saatiyle misafirin ŞU AN gördüğü menü bu mu? */
    isServingNow: boolean;
};

export type MenuScreenActionsProps = {
    /** Şeridin erişilebilir adı. */
    label: string;
    /** Menü haplarının erişilebilir grup adı. */
    menusLabel: string;
    menus: MenuPill[];
    onSelectMenu: (menuId: number) => void;
    addMenuLabel: string;
    onAddMenu: () => void;
    editMenuLabel: string;
    onEditMenu: (menuId: number) => void;
    /** "şimdi açık" — hapın durumunu RENKTEN BAĞIMSIZ anlatan kelime. */
    servingNowLabel: string;
    photoImport: PhotoImportState;
    csvLabel: string;
    onCsv: () => void;
    previewAndPublishLabel: string;
    onPreviewAndPublish: (() => void) | null;
    addProductLabel: string;
    onAddProduct: () => void;
    /** Şeridin altına açılan panel (CSV kutusu, fotoğraf okuma sihirbazı, menü formu). */
    children?: ReactNode;
};

const secondaryClass = clsx(
    'inline-flex min-h-[var(--control-height)] shrink-0 items-center gap-[var(--space-2)]',
    'rounded-[var(--radius-md)] border border-border bg-[var(--color-surface)] px-[var(--space-4)] py-[var(--space-2)]',
    'text-body font-medium text-fg',
    'hover:bg-surface-hover',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
);

const primaryClass = clsx(
    'inline-flex min-h-[var(--control-height)] shrink-0 items-center gap-[var(--space-2)]',
    'rounded-[var(--radius-md)] border border-action bg-action px-[var(--space-4)] py-[var(--space-2)]',
    // Ağırlık 700. `font-semibold` (600) ara basamaktır ve 500 ile 700
    // arasında gözle ayırt edilmesi zor üçüncü bir kademe açar.
    'text-body font-bold text-action-fg',
    'hover:brightness-95',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
    'forced-colors:border-[ButtonText]',
);

const pillClass = clsx(
    'inline-flex min-h-[var(--control-height)] shrink-0 items-center gap-[var(--space-2)]',
    'rounded-pill border px-[var(--space-4)] py-[var(--space-1)]',
    'text-body text-fg',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
);

/**
 * MENÜ EKRANININ ÜST ŞERİDİ — kanonik kaynak
 * `docs/reference/panel-v3/panel.dc.html`, `data-screen-label="Menüler"`.
 *
 * NE DEĞİŞTİ VE NEDEN — MENÜ HAPLARI DOĞDU
 *
 * Bu şerit 2026-09-05'e kadar tek bir KİMLİK ÇİPİ çiziyordu ve o çipin
 * başındaki yorum, hapların neden çizilmediğini üç maddede açıklıyordu:
 * veri modeli şube başına tek menü tutuyordu (`menus.location_id` UNIQUE),
 * kaynağın kendisi de hapları modellemiyordu (tıklandıklarında yalnız bir
 * bildirim gösteriyorlardı) ve "tıklanınca hiçbir şey yapmayan üç düğme"
 * çizmek dürüst değildi.
 *
 * O gerekçenin ilk maddesi ARTIK GEÇERSİZ. Sahibe açıkça soruldu ve
 * "çoklu menü YAPILSIN, saat bazlı geçişli" dedi (`docs/109` §7.1). Kilit
 * gevşetildi, şube başına birden çok menü ve her menüye bir saat aralığı
 * geldi. Yani haplar artık bir veri modeline dayanıyor.
 *
 * Üçüncü madde ise hâlâ geçerli ve bu bileşen ona uyar: HAPLAR SAHTE
 * DEĞİLDİR. Bir hapa basmak gerçekten O MENÜNÜN kategorilerini ve
 * ürünlerini getirir; ad `menus.name`'den, ipucu şubenin gerçek geçiş
 * anlarından gelir. "Şimdi açık" işareti yalnız renkle değil KELİMEYLE de
 * anlatılır — rengi ayırt edemeyen biri de hangi menünün masada olduğunu
 * okuyabilmeli.
 */
export function MenuScreenActions({
    label,
    menusLabel,
    menus,
    onSelectMenu,
    addMenuLabel,
    onAddMenu,
    editMenuLabel,
    onEditMenu,
    servingNowLabel,
    photoImport,
    csvLabel,
    onCsv,
    previewAndPublishLabel,
    onPreviewAndPublish,
    addProductLabel,
    onAddProduct,
    children,
}: MenuScreenActionsProps) {
    const selected = menus.find((menu) => menu.isSelected) ?? null;

    return (
        <div className="flex flex-col gap-[var(--space-4)]">
            <div className="flex flex-wrap items-end justify-between gap-[var(--space-4)]">
                {/*
                    MENÜ HAPLARI. Seçili menünün adı aynı zamanda ekranın
                    BAŞLIĞIDIR; hap olması onu başlık olmaktan çıkarmaz.
                    `<h2>` kalırsa ekran okuyucu kullanan biri başlık
                    listesinden doğrudan buraya atlayabilir.
                */}
                <div
                    role="group"
                    aria-label={menusLabel}
                    className="flex flex-wrap items-center gap-[var(--space-2)]"
                >
                    {menus.map((menu) => {
                        /*
                            SEÇİLİ MENÜNÜN ADI EKRANIN BAŞLIĞIDIR ve
                            başlığın erişilebilir adı YALNIZ ADDIR. Saat
                            ipucunu başlığın içine almak, başlık listesinde
                            "Ana menü 11:00–07:00 şimdi açık" diye okunan
                            bir satır üretirdi ve ekran okuyucu kullanan
                            biri menüyü adıyla bulamazdı.
                        */
                        const nameNode = menu.isSelected ? (
                            <h2 className="text-body font-bold">{menu.name}</h2>
                        ) : (
                            <span className="text-body font-medium">{menu.name}</span>
                        );

                        return (
                            <button
                                key={menu.id}
                                type="button"
                                /*
                                    `aria-pressed`: hap bir SEÇİM'dir, bir
                                    bağlantı değil. Seçiliyi yalnız çerçeve
                                    kalınlığıyla anlatmak, ekran okuyucu
                                    kullanan birine hangi menüye baktığını
                                    hiç söylemezdi.
                                */
                                aria-pressed={menu.isSelected}
                                onClick={() => onSelectMenu(menu.id)}
                                className={clsx(
                                    pillClass,
                                    menu.isSelected
                                        ? 'border-action bg-[var(--color-surface)]'
                                        : 'border-border bg-[var(--color-surface)] hover:bg-surface-hover',
                                )}
                            >
                                {nameNode}
                                <span className="text-meta tabular-nums text-fg-secondary">
                                    {menu.hint}
                                </span>
                                {menu.isServingNow ? (
                                    <span className="text-meta text-fg-secondary">
                                        {servingNowLabel}
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}

                    {/*
                        "+" — kaynağın hap sırasındaki yeni menü düğmesi.
                        İkon tek başına ne yaptığını söylemez; erişilebilir
                        adı metindir.
                    */}
                    <button
                        type="button"
                        aria-label={addMenuLabel}
                        onClick={onAddMenu}
                        className={clsx(pillClass, 'border-border hover:bg-surface-hover')}
                    >
                        <Plus size={18} aria-hidden="true" />
                    </button>

                    {selected !== null ? (
                        <button
                            type="button"
                            onClick={() => onEditMenu(selected.id)}
                            className={clsx(pillClass, 'border-border hover:bg-surface-hover')}
                        >
                            {editMenuLabel}
                        </button>
                    ) : null}
                </div>

                <div
                    role="group"
                    aria-label={label}
                    className="flex flex-wrap items-center gap-[var(--space-2)]"
                >
                    {/*
                        SIRA RASTGELE DEĞİL: soldan sağa "çok iş → az iş".
                        Bütün bir menüyü getirmek en solda; tek ürün
                        eklemek en sağda ve tek dolgulu düğme odur. Ters
                        olsaydı ilk kez menü kuran sahip en sağdaki
                        birincil düğmeye uzanır, altmış ürünü tek tek
                        eklemeye başlardı.
                    */}
                    {photoImport.kind === 'available' ? (
                        <button
                            type="button"
                            onClick={photoImport.onClick}
                            className={secondaryClass}
                        >
                            <Sparkle size={18} weight="fill" aria-hidden="true" />
                            {photoImport.label}
                        </button>
                    ) : null}
                    <button type="button" onClick={onCsv} className={secondaryClass}>
                        <FileCsv size={18} aria-hidden="true" />
                        {csvLabel}
                    </button>
                    {onPreviewAndPublish !== null ? (
                        <button
                            type="button"
                            onClick={onPreviewAndPublish}
                            className={secondaryClass}
                        >
                            <Eye size={18} aria-hidden="true" />
                            {previewAndPublishLabel}
                        </button>
                    ) : null}
                    <button type="button" onClick={onAddProduct} className={primaryClass}>
                        <Plus size={18} aria-hidden="true" />
                        {addProductLabel}
                    </button>
                </div>
            </div>

            {/*
                AI YOKSA DÜĞMENİN YERİNE SEBEP. Boşluk bırakmak, sahibin
                dün gördüğü düğmeyi bugün aramasına yol açardı.
            */}
            {photoImport.kind === 'blocked' ? (
                <p className="text-meta text-fg-secondary">{photoImport.reason}</p>
            ) : null}

            {children}
        </div>
    );
}

export default MenuScreenActions;
