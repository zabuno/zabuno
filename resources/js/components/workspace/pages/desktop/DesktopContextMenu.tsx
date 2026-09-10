import { useEffect, useRef, type ReactNode } from 'react';

/**
 * SAĞ TIK MENÜSÜ — ve onun KLAVYE karşılığı (`docs/153` §7, WCAG 2.2 AA).
 *
 * Menü yalnız fareyle açılabilseydi, klavyeyle çalışan kullanıcı buradaki
 * eylemlere hiç ulaşamazdı. Bu yüzden açan taraf iki yolu da bağlar
 * (`contextmenu` olayı ve Shift+F10 / Menü tuşu) ve ikisi de AYNI menüyü
 * açar: ayrı bir "klavye menüsü" yazmak, iki menünün zamanla ayrışması
 * demekti.
 *
 * Bileşen yalnız KABUKtur: konum, dışarı tıklamayla ve Escape ile kapanma,
 * ilk öğeye odak. Hangi eylemlerin durduğunu ve ne yaptıklarını bilmez —
 * o ekranın kararıdır.
 *
 * Hiç geçiş/animasyon yok: `prefers-reduced-motion` MUTLAKtır ve ona
 * uymanın en ucuz yolu, uyulacak bir hareket hiç üretmemektir.
 */
export type DesktopContextMenuItem = {
    key: string;
    label: string;
    icon?: ReactNode;
    danger?: boolean;
    onSelect: () => void;
};

export function DesktopContextMenu({
    label,
    x,
    y,
    items,
    onClose,
}: {
    label: string;
    x: number;
    y: number;
    items: DesktopContextMenuItem[];
    onClose: () => void;
}) {
    const menuRef = useRef<HTMLDivElement | null>(null);

    /* Menü açıkken dışarı tıklamak kapatır. */
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
            style={{ position: 'fixed', left: x, top: y }}
            className="z-50 flex min-w-[12rem] flex-col rounded-[var(--radius-md)] border border-border bg-surface p-1 shadow-lg"
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    onClose();
                }
            }}
        >
            {items.map((item, index) => (
                <button
                    key={item.key}
                    type="button"
                    role="menuitem"
                    /*
                        Odak İLK öğeye gider: menüyü klavyeyle açan kişi,
                        açtığı yerde bir şeyin seçili olduğunu görmeli —
                        yoksa menü açılır ve odak hiçbir yerde olmaz.
                    */
                    autoFocus={index === 0}
                    onClick={item.onSelect}
                    className={[
                        'flex items-center gap-2 rounded-[var(--radius-sm)] px-[var(--space-3)] py-[var(--space-2)] text-start text-body hover:bg-surface-accent',
                        item.danger === true ? 'text-fg-danger' : 'text-fg',
                    ].join(' ')}
                >
                    {item.icon}
                    {item.label}
                </button>
            ))}
        </div>
    );
}

export default DesktopContextMenu;
