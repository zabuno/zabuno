import { useState, type DragEvent } from 'react';
import clsx from 'clsx';
import { DotsSixVertical, Plus } from '@phosphor-icons/react';

export type CategoryRailEntry = {
    id: number;
    name: string;
    /** Kategorideki ürün adedi — rayın ikinci yarısı, boş kategoriyi açmadan gösterir. */
    count: number;
};

export type CategoryRailProps = {
    categories: CategoryRailEntry[];
    activeCategoryId: number | null;
    onSelect: (categoryId: number) => void;
    onAddCategory: () => void;
    /** Rayın erişilebilir adı. */
    listLabel: string;
    /** Ekleme düğmesinin metni ("Kategori"). */
    addLabel: string;
    /** Sayıyı cümleye çeviren biçimlendirici — çeviri katmanı çağıranındır. */
    countLabel: (count: number) => string;
    /**
     * Sürükleyerek yeniden sıralama. Verilmezse tutamaç HİÇ ÇİZİLMEZ:
     * görünüp de çalışmayan bir tutamaç, kullanıcıya olmayan bir söz
     * vermektir.
     */
    onReorder?: (sourceCategoryId: number, targetCategoryId: number) => void;
    /** Tutamacın `title`'ı — fare kullanıcısına ne yapacağını söyler. */
    reorderHandleLabel?: (name: string) => string;
};

/**
 * KATEGORİ RAYI — kanonik kaynak `docs/reference/panel-v3/panel.dc.html`
 * satır 30256-30261.
 *
 * NE DEĞİŞTİ VE NEDEN
 *
 * Bu ekran daha önce her kategoriyi kendi kartıyla, alt alta çiziyordu.
 * Altı kategorili bir dönerci düşünün: "Tatlılar"daki künefenin fiyatını
 * düzeltmek isteyen sahip, önündeki beş kategorinin bütün ürünlerinin
 * arasından kaydırarak geçmek zorundaydı. Üstelik aradığı kategorinin
 * ekrandaki yeri SABİT DEĞİLDİ — konumu kendinden önceki kategorilerin
 * ürün sayısına bağlıydı, yani dün ortada olan bugün en altta olabilirdi.
 *
 * Ray o bağı koparır. Kategori listesi hep aynı yerdedir ve uzunluğu
 * ürün sayısıyla değişmez; seçim, sağdaki ürün listesini değiştirir.
 * "Kaydırarak ara" işi "tıkla" işine iner.
 *
 * 320px'de ray YATAY bir şeride döner (`flex-row` + yatay kaydırma):
 * dar ekranda 240px'lik bir sütun ile ürün listesi yan yana sığmaz ve
 * ikisini de daraltmak ikisini de okunmaz yapardı.
 */
export function CategoryRail({
    categories,
    activeCategoryId,
    onSelect,
    onAddCategory,
    listLabel,
    addLabel,
    countLabel,
    onReorder,
    reorderHandleLabel,
}: CategoryRailProps) {
    const [draggingId, setDraggingId] = useState<number | null>(null);

    return (
        <nav
            aria-label={listLabel}
            className={clsx(
                'flex gap-[var(--space-1)] rounded-[var(--radius-lg)] border border-border bg-[var(--color-surface)] p-[var(--space-2)]',
                // Dar ekranda yatay şerit, geniş ekranda sütun.
                'flex-row overflow-x-auto md:flex-col md:overflow-x-visible',
                'forced-colors:border-[CanvasText]',
            )}
        >
            {categories.map((category) => {
                const active = category.id === activeCategoryId;

                return (
                    <div
                        key={category.id}
                        /*
                            SÜRÜKLEMENİN HEDEFİ SATIRIN TAMAMIDIR, tutamacın
                            kendisi değil: 24px'lik bir noktaya isabet
                            ettirmek fareyle bile zordur ve ıskalanan her
                            bırakma sessizce hiçbir şey yapmaz.
                        */
                        onDragOver={(event: DragEvent<HTMLDivElement>) => {
                            // `preventDefault` olmadan tarayıcı bırakmayı hiç
                            // kabul etmez — HTML'in varsayılanı "burası
                            // bırakma alanı değil"dir.
                            if (draggingId !== null) {
                                event.preventDefault();
                            }
                        }}
                        onDrop={(event: DragEvent<HTMLDivElement>) => {
                            event.preventDefault();
                            const source = draggingId;
                            setDraggingId(null);

                            if (source !== null && source !== category.id) {
                                onReorder?.(source, category.id);
                            }
                        }}
                        className={clsx(
                            'flex shrink-0 items-center md:w-full',
                            draggingId !== null &&
                                draggingId !== category.id &&
                                'outline-2 outline-offset-2 outline-focus',
                        )}
                    >
                        {onReorder !== undefined ? (
                            <span
                                draggable
                                title={reorderHandleLabel?.(category.name)}
                                /*
                                    EKRAN OKUYUCUYA SUNULMAZ ve bu bir
                                    eksiklik değil: aynı işi yapan
                                    "yukarı/aşağı" düğmeleri kategori
                                    başlığında duruyor ve ADLARINI taşıyor.
                                    Bir sürükleme jestini ekran okuyucuya
                                    duyurmak, yapılamayacak bir şeyi önermek
                                    olurdu.
                                */
                                aria-hidden="true"
                                onDragStart={() => setDraggingId(category.id)}
                                onDragEnd={() => setDraggingId(null)}
                                className="flex h-[var(--density-row-height)] w-[var(--space-5)] cursor-grab items-center justify-center text-fg-secondary active:cursor-grabbing"
                            >
                                <DotsSixVertical size={18} weight="bold" />
                            </span>
                        ) : null}
                        <button
                            type="button"
                            /*
                            DURUM YALNIZ RENKLE ANLATILMAZ (`DESIGN_SPEC`
                            §12). Kaynakta seçili kategori sadece dolgu ve
                            kalın yazıyla ayrılıyor; ikisi de ekran
                            okuyucuya ulaşmaz. `aria-current` aynı bilgiyi
                            makinenin okuyabildiği biçimde taşır.
                        */
                            aria-current={active ? true : undefined}
                            onClick={() => onSelect(category.id)}
                            className={clsx(
                                'flex min-h-[var(--density-row-height)] min-w-0 flex-1 shrink-0 items-center gap-[var(--space-3)]',
                                'rounded-[var(--radius-md)] px-[var(--space-3)] text-start text-body text-fg',
                                // Ağırlık 500 → 700; ara basamak (600) yok.
                                active ? 'bg-surface-active font-bold' : 'font-medium',
                                'hover:bg-surface-hover',
                                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                            )}
                        >
                            <span className="min-w-0 flex-1 truncate">{category.name}</span>
                            {/*
                            SAYI `tabular-nums`: rakamlar eşit genişlikte
                            olmazsa alt alta dizilen sayılar hizalanmaz ve
                            "hangi kategori boş" sorusu gözle
                            cevaplanamaz.
                        */}
                            <span className="shrink-0 tabular-nums text-fg-secondary">
                                {countLabel(category.count)}
                            </span>
                        </button>
                    </div>
                );
            })}
            {/*
                EKLEME DÜĞMESİ LİSTENİN SONUNDA. Başta olsaydı, sahip her
                kategori aramasında önce onun üzerinden geçerdi: en sık
                yapılan iş (var olanı seçmek), en seyrek yapılanın (yeni
                açmak) arkasında kalırdı.
            */}
            <button
                type="button"
                onClick={onAddCategory}
                className={clsx(
                    'flex min-h-[var(--density-row-height)] shrink-0 items-center gap-[var(--space-2)]',
                    'rounded-[var(--radius-md)] border border-dashed border-border px-[var(--space-3)]',
                    'text-start text-body font-medium text-fg-secondary',
                    'hover:bg-surface-hover hover:text-fg',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                )}
            >
                <Plus size={16} aria-hidden="true" />
                {addLabel}
            </button>
        </nav>
    );
}

export default CategoryRail;
