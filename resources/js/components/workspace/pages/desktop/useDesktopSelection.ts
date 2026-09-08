import { useCallback, useRef, useState } from 'react';

/**
 * KLAVYEYLE GEZİLEN, ÇOKLU SEÇİLEBİLEN LİSTE — masaüstünün ortak dokusu
 * (`docs/151` §B).
 *
 * `docs/153` sipariş kuyruğunda bu davranışı elle yazmıştı. İkinci ve
 * üçüncü ekran gelince aynı yüz satırı üç kez yazmak iki şeyi birden
 * getirirdi: üç ayrı kusur yüzeyi ve zamanla ÜÇ FARKLI kısayol sözlüğü.
 * Sahip aynı panelde bir ekranda Boşluk'la, ötekinde Ctrl+tık ile seçseydi
 * ikisini de öğrenmek zorunda kalırdı.
 *
 * Kanca YALNIZ SEÇİM DURUMUNU tutar; hiçbir şey çizmez ve hiçbir veri
 * okumaz. Böylece ızgara (medya), tablo (ekip) ve liste (puanlar) aynı
 * kuralı paylaşır ama kendi düzenlerini kendileri kurar.
 *
 * ── Kurallar tek cümleyle söylenebilir olmalı ─────────────────────────
 *
 * Kullanıcı Enter'a basmadan önce ne olacağını bilmek zorundadır:
 * "seçiliyse seçim, değilse üzerindeki" (`targetsFor`). Bağlam menüsü,
 * toplu şerit ve klavye aynı cümleyi kullanır.
 *
 * ── Neden türetilmiş değer ────────────────────────────────────────────
 *
 * Liste değişince (bir dosya silinince) seçim de değişmeli — ama bir
 * ETKİYLE değil, TÜRETEREK. Etkiyle kurulan bir eşitleme, listenin
 * değiştiği kare ile seçimin düzeldiği kare arasında BİR KARE boyunca
 * yanlış durumu çizer; o karede "toplu sil" ekranda olmayan bir kimliği
 * hedefler ve sunucu 404 döner.
 */

export type DesktopSelection = {
    /** Klavye odağının ÜZERİNDE olduğu satır (roving tabindex). */
    activeId: number | null;
    /** Seçili satırlar — her zaman görünen listenin bir alt kümesi. */
    selectedIds: number[];
    isSelected: (id: number) => boolean;
    /** Tıklama: `additive` = Ctrl/Cmd, `range` = Shift. */
    activate: (id: number, additive: boolean, range: boolean) => void;
    /** Ok tuşu: `extend` = Shift ile aralık büyütme. */
    move: (delta: number, extend: boolean) => void;
    toggle: (id: number) => void;
    selectAll: () => void;
    clear: () => void;
    /** Eylemin hedefi: seçim varsa ve satır onun içindeyse seçim, değilse satır. */
    targetsFor: (id: number | null) => number[];
    registerRef: (id: number) => (node: HTMLElement | null) => void;
    focusRow: (id: number) => void;
    /**
     * Satırın DOM düğümü — satır/sütun geometrisi soran ızgaralar için.
     * `null` dönmesi normaldir (henüz çizilmemiş ya da düzen motoru yok).
     */
    nodeFor: (id: number) => HTMLElement | null;
};

export function useDesktopSelection(ids: number[]): DesktopSelection {
    const [activeIdRaw, setActiveId] = useState<number | null>(null);
    const [selectedIdsRaw, setSelectedIds] = useState<number[]>([]);
    /** Shift ile aralık seçerken sabit kalan uç. */
    const [anchorId, setAnchorId] = useState<number | null>(null);

    const refs = useRef(new Map<number, HTMLElement>());

    const selectedIds = selectedIdsRaw.filter((id) => ids.includes(id));
    const activeId =
        activeIdRaw !== null && ids.includes(activeIdRaw) ? activeIdRaw : (ids[0] ?? null);

    const focusRow = useCallback((id: number) => {
        refs.current.get(id)?.focus();
    }, []);

    const registerRef = useCallback(
        (id: number) => (node: HTMLElement | null) => {
            if (node === null) {
                refs.current.delete(id);
            } else {
                refs.current.set(id, node);
            }
        },
        [],
    );

    const nodeFor = useCallback((id: number) => refs.current.get(id) ?? null, []);

    const rangeTo = useCallback(
        (id: number) => {
            const from = ids.indexOf(anchorId ?? activeId ?? id);
            const to = ids.indexOf(id);

            if (from === -1 || to === -1) {
                return [id];
            }

            return ids.slice(Math.min(from, to), Math.max(from, to) + 1);
        },
        [ids, anchorId, activeId],
    );

    const activate = useCallback(
        (id: number, additive: boolean, range: boolean) => {
            setActiveId(id);

            if (range) {
                setSelectedIds(rangeTo(id));

                return;
            }

            if (additive) {
                setAnchorId(id);
                setSelectedIds((current) =>
                    current.includes(id)
                        ? current.filter((candidate) => candidate !== id)
                        : [...current, id],
                );

                return;
            }

            /*
                DÜZ TIKLAMA SEÇİMİ TEMİZLER ve bu masaüstünün her yerinde
                aynıdır. Seçimi koruyup üzerine eklemek, sekiz dosya seçili
                bir ızgarada "Sil"e basan birine dokuz dosya sildirirdi.
            */
            setAnchorId(id);
            setSelectedIds([]);
        },
        [rangeTo],
    );

    const move = useCallback(
        (delta: number, extend: boolean) => {
            if (ids.length === 0) {
                return;
            }

            const current = activeId === null ? 0 : ids.indexOf(activeId);
            const next = Math.min(Math.max(current + delta, 0), ids.length - 1);
            const nextId = ids[next];

            if (nextId === undefined) {
                return;
            }

            setActiveId(nextId);

            if (extend) {
                setSelectedIds(rangeTo(nextId));
            } else {
                setAnchorId(nextId);
            }

            refs.current.get(nextId)?.focus();
        },
        [ids, activeId, rangeTo],
    );

    const toggle = useCallback((id: number) => {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((candidate) => candidate !== id)
                : [...current, id],
        );
    }, []);

    const selectAll = useCallback(() => {
        setSelectedIds(ids);
    }, [ids]);

    const clear = useCallback(() => {
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
        activate,
        move,
        toggle,
        selectAll,
        clear,
        targetsFor,
        registerRef,
        focusRow,
        nodeFor,
    };
}
