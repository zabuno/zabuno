import { useCallback, useRef, useState } from 'react';

/**
 * İŞARETLEYİCİ/KLAVYE LİSTESİNİN SEÇİM MEKANİĞİ — `docs/153` §6.
 *
 * Masaüstünde bir listeyle çalışmak dört hareketten ibarettir ve dördü de
 * telefonda YOKTUR: oklarla gezinmek, boşlukla işaretlemek, Shift ile
 * aralık almak, Ctrl/Cmd+A ile hepsini almak. Bu dosya yalnız o mekaniği
 * taşır — hangi eylemin çalıştırılacağını, satırın neye benzediğini ve
 * hangi cümlenin yazılacağını bilmez.
 *
 * ## Neden ayrı bir dosya
 *
 * Aynı mekanik masaüstüne taşınan HER ekranda tekrar eder (kuyruk, medya,
 * yarın menü). Her ekran kendi kopyasını taşısaydı, "Shift+ok aralık
 * seçer" kuralı bir ekranda düzeltilip ötekinde unutulurdu ve sahip aynı
 * tuşun iki ekranda farklı davrandığını görürdü. Sipariş kuyruğu bugün
 * hâlâ kendi kopyasıyla çalışıyor; onu buraya taşımak AYRI bir pakettir
 * ve bu paket onu sessizce değiştirmez.
 *
 * ## Etki YOK, TÜRETME var
 *
 * Liste değişince (bir dosya silinince) seçim de değişmeli. Bu, `useEffect`
 * içinde `setState` ile yapılsaydı, listenin değiştiği kare ile seçimin
 * düzeldiği kare arasında BİR KARE boyunca ekranda olmayan bir satır
 * seçili görünürdü — ve o satıra "sil" denebilirdi. Ham durum saklanır,
 * ekrana giden değer her çizimde listeden süzülür.
 */
export type DesktopSelection = {
    /** Klavye odağının ÜZERİNDE olduğu satır (roving tabindex). */
    activeId: number | null;
    selectedIds: number[];
    isSelected: (id: number) => boolean;
    /** Satır düğümünü kaydeder; odak taşımak için gerekir. */
    registerRow: (id: number) => (node: HTMLElement | null) => void;
    focusRow: (id: number) => void;
    /** Satırın DOM dikdörtgeni — bağlam menüsünü klavyeden konumlamak için. */
    rowRect: (id: number) => DOMRect | null;
    setActive: (id: number) => void;
    moveActive: (delta: number, extend: boolean) => void;
    toggleSelected: (id: number) => void;
    selectRange: (id: number) => void;
    /** Tek satıra iner: seçim temizlenir, çıpa buraya taşınır. */
    selectOnly: (id: number) => void;
    selectAll: () => void;
    clearSelection: () => void;
    /**
     * Eylemin HEDEFİ: seçim varsa ve verilen satır onun içindeyse seçim,
     * değilse yalnız o satır.
     *
     * Kural tek cümleyle söylenebilir olmalı, çünkü kullanıcı tuşa basmadan
     * önce neyin olacağını bilmek zorundadır. Bağlam menüsü ve toplu şerit
     * de aynı cümleyi kullanır.
     */
    targetsFor: (id: number | null) => number[];
};

export function useDesktopSelection(ids: readonly number[]): DesktopSelection {
    const [activeIdRaw, setActiveId] = useState<number | null>(null);
    const [selectedIdsRaw, setSelectedIds] = useState<number[]>([]);
    /** Shift ile aralık seçerken sabit kalan uç. */
    const [anchorId, setAnchorId] = useState<number | null>(null);

    const rowRefs = useRef(new Map<number, HTMLElement>());

    const selectedIds = selectedIdsRaw.filter((id) => ids.includes(id));
    const activeId =
        activeIdRaw !== null && ids.includes(activeIdRaw) ? activeIdRaw : (ids[0] ?? null);

    const registerRow = useCallback(
        (id: number) => (node: HTMLElement | null) => {
            if (node === null) {
                rowRefs.current.delete(id);

                return;
            }

            rowRefs.current.set(id, node);
        },
        [],
    );

    const focusRow = useCallback((id: number) => {
        rowRefs.current.get(id)?.focus();
    }, []);

    const rowRect = useCallback(
        (id: number) => rowRefs.current.get(id)?.getBoundingClientRect() ?? null,
        [],
    );

    const moveActive = useCallback(
        (delta: number, extend: boolean) => {
            if (ids.length === 0) {
                return;
            }

            const currentIndex = activeId === null ? -1 : ids.indexOf(activeId);
            const nextIndex = Math.min(
                ids.length - 1,
                Math.max(0, (currentIndex === -1 ? 0 : currentIndex) + delta),
            );
            const nextId = ids[nextIndex];

            if (nextId === undefined) {
                return;
            }

            setActiveId(nextId);
            focusRow(nextId);

            if (extend) {
                /*
                    Shift ile gezinmek SEÇİMİ BÜYÜTÜR: çıpa sabit kalır ve
                    aradaki her satır seçilir. Çıpa yoksa gezinti başladığı
                    yer çıpa olur.
                */
                const anchor = anchorId ?? activeId ?? nextId;
                const anchorIndex = ids.indexOf(anchor);

                if (anchorIndex !== -1) {
                    const from = Math.min(anchorIndex, nextIndex);
                    const to = Math.max(anchorIndex, nextIndex);

                    setAnchorId(anchor);
                    setSelectedIds(ids.slice(from, to + 1));
                }

                return;
            }

            setAnchorId(nextId);
        },
        [activeId, anchorId, focusRow, ids],
    );

    const toggleSelected = useCallback((id: number) => {
        setSelectedIds((current) =>
            current.includes(id) ? current.filter((other) => other !== id) : [...current, id],
        );
        setAnchorId(id);
    }, []);

    const selectRange = useCallback(
        (id: number) => {
            const anchor = anchorId ?? id;
            const from = ids.indexOf(anchor);
            const to = ids.indexOf(id);

            if (from === -1 || to === -1) {
                return;
            }

            setSelectedIds(ids.slice(Math.min(from, to), Math.max(from, to) + 1));
        },
        [anchorId, ids],
    );

    const selectOnly = useCallback((id: number) => {
        setAnchorId(id);
        setSelectedIds([]);
    }, []);

    const selectAll = useCallback(() => {
        setSelectedIds([...ids]);
    }, [ids]);

    const clearSelection = useCallback(() => {
        setSelectedIds([]);
    }, []);

    const targetsFor = useCallback(
        (id: number | null): number[] => {
            if (id === null) {
                return [];
            }

            return selectedIds.length > 1 && selectedIds.includes(id) ? selectedIds : [id];
        },
        [selectedIds],
    );

    return {
        activeId,
        selectedIds,
        isSelected: (id) => selectedIds.includes(id),
        registerRow,
        focusRow,
        rowRect,
        setActive: setActiveId,
        moveActive,
        toggleSelected,
        selectRange,
        selectOnly,
        selectAll,
        clearSelection,
        targetsFor,
    };
}

export default useDesktopSelection;
