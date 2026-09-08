import { useEffect, useRef, type ReactNode } from 'react';

/**
 * BAĞLAM MENÜSÜ — İMLEÇLE VE KLAVYEYLE AYNI MENÜ (`docs/151` §B,
 * `docs/153` §7).
 *
 * WCAG 2.2 AA'nın buradaki şartı tek cümledir: sağ tıkla ulaşılan her
 * eylemin bir klavye yolu olmalı. Bu bileşen o yolu MENÜNÜN KENDİSİNDE
 * çözer — konumu açan taraf verir (işaretçiden ya da satırın sol alt
 * köşesinden), menü ikisini de aynı biçimde çizer.
 *
 * AYRI BİR "KLAVYE MENÜSÜ" YAZILMADI ve yazılmamalı: iki menü zamanla
 * ayrışır ve bir gün yalnız fareyle ulaşılabilen bir eylem doğar. O günü
 * kimse fark etmez, çünkü fareyle test eden herkes için her şey yerindedir.
 *
 * Kapanma yolları: dışarı tıklama, Escape ve odak kaybı. Escape odağı
 * AÇILDIĞI SATIRA geri verir — vermeseydi klavye kullanıcısı menüyü
 * kapattığında listenin başına düşerdi.
 */

export type DesktopMenuPosition = { x: number; y: number };

export function DesktopContextMenu({
    label,
    position,
    onClose,
    children,
}: {
    label: string;
    position: DesktopMenuPosition;
    /** Kapanınca çağrılır; odağı geri vermek çağıranın işidir. */
    onClose: () => void;
    children: ReactNode;
}) {
    const menuRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (menuRef.current !== null && !menuRef.current.contains(event.target as Node)) {
                onClose();
            }
        };

        document.addEventListener('mousedown', close);

        return () => {
            document.removeEventListener('mousedown', close);
        };
    }, [onClose]);

    return (
        <div
            ref={menuRef}
            role="menu"
            aria-label={label}
            style={{ position: 'fixed', left: position.x, top: position.y }}
            className="dk-menu"
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    onClose();
                }
            }}
        >
            {children}
        </div>
    );
}

/**
 * Menü maddesi. `autoFocus` yalnız İLK maddeye verilir (çağıranın işi):
 * menü açıldığında odak içeride olmazsa Escape'i yakalayacak kimse olmaz.
 */
export function DesktopMenuItem({
    onSelect,
    tone,
    autoFocus,
    children,
}: {
    onSelect: () => void;
    tone?: 'danger';
    autoFocus?: boolean;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="menuitem"
            autoFocus={autoFocus}
            onClick={onSelect}
            className="dk-menuitem"
            {...(tone === undefined ? {} : { 'data-tone': tone })}
        >
            {children}
        </button>
    );
}

/**
 * Sağ tıkın klavye karşılığı: `Shift+F10` her masaüstü tarayıcısında,
 * `ContextMenu` ise o tuşu taşıyan klavyelerde.
 *
 * Konum satırın SOL ALT köşesidir — imleçten açıldığında imlecin olduğu
 * yer. `getBoundingClientRect` düzen motoru olmayan bir ortamda sıfır
 * döner; menü o zaman sol üst köşede çizilir ve YİNE ÇALIŞIR. Ölçüm
 * yapılamadığında eylemi iptal etmek, klavye kullanıcısını menüsüz
 * bırakırdı.
 */
export function menuPositionFor(node: HTMLElement | null): DesktopMenuPosition {
    const rect = node?.getBoundingClientRect() ?? null;

    return { x: rect?.left ?? 0, y: rect?.bottom ?? 0 };
}

export function isContextMenuKey(event: { key: string; shiftKey: boolean }): boolean {
    return event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10');
}
