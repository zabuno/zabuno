import { useCallback, useEffect, useId, useRef, useState, type ReactNode } from 'react';
import clsx from 'clsx';
import { CaretDown } from '@phosphor-icons/react';
import { MenuItem } from '../micro/MenuItem';
import { MenuItemRadio } from '../micro/MenuItemRadio';

export type SidebarMenuItem = {
    key: string;
    label: string;
    onSelect: () => void;
    icon?: ReactNode;
    disabled?: boolean;
    destructive?: boolean;
    /** İşaretli satır — hangi çalışma alanındayız gibi. */
    selected?: boolean;
};

export type SidebarMenuProps = {
    /** Tetikleyicinin erişilebilir adı; görünen içerik ayrıdır. */
    label: string;
    triggerContent: ReactNode;
    /**
     * Panel AŞAĞI mı YUKARI mı açılır.
     *
     * Kenar çubuğunun tepesindeki çalışma alanı seçici aşağı, dibindeki
     * hesap menüsü yukarı açılır. Yön bir stil tercihi değil: panel ekranın
     * dışına taşarsa menü kullanılamaz hâle gelir.
     */
    placement?: 'down' | 'up';
    header?: ReactNode;
    items: SidebarMenuItem[];
    radioGroup?: {
        label: string;
        options: Array<{ key: string; label: string }>;
        value: string;
        onSelect: (key: string) => void;
    };
    className?: string;
};

/** Panel kapanırken çıkış animasyonu için DOM'da kalır. */
type PanelState = 'closed' | 'open' | 'closing';

/**
 * Kenar çubuğunun kendi menüsü — sahibin isteği (2026-09-04).
 *
 * "Bu butonu dropdown gibi düşün. Sağ tarafında chevron ok işareti, ama
 * yukarıya işaret ediyor (drop down vs drop up) ve açılan panel ile genişliği
 * aynı, açılan panel ise bu buton ile bütünleşik açılıyor. Ease başlasın ease
 * bitsin."
 *
 * Neden `ActionMenu` (Flowbite `Dropdown`) kullanılmadı: o panel yüzen bir
 * katmandır ve genişliğini kendi içeriğinden alır. Tetikleyiciyle aynı
 * genişlikte ve ona YAPIŞIK bir panel, yüzen konumlandırmayla ancak her
 * açılışta ölçüm yaparak taklit edilebilirdi — ve ölçüm bir kare geç
 * geldiğinde panel önce yanlış genişlikte görünürdü. Burada panel normal
 * akışın içinde, tetikleyicinin kutusuna göre konumlanır: genişlik
 * eşitliği bir hesap değil, bir sonuçtur.
 *
 * Yüzen menülerin geri kalanı (satır eylemleri, oluştur menüsü) `ActionMenu`
 * olarak kalır; bu bileşen onun yerini almaz, yanında durur.
 */
export function SidebarMenu({
    label,
    triggerContent,
    placement = 'down',
    header,
    items,
    radioGroup,
    className,
}: SidebarMenuProps) {
    const panelId = useId();
    const [state, setState] = useState<PanelState>('closed');
    const rootRef = useRef<HTMLDivElement | null>(null);
    const triggerRef = useRef<HTMLButtonElement | null>(null);
    const panelRef = useRef<HTMLDivElement | null>(null);

    const open = state === 'open';
    const mounted = state !== 'closed';

    /*
        Kapanış İKİ ADIMDIR: önce çıkış sınıfı, sonra DOM'dan çıkarma. Tek
        adımda kaldırsaydık panel bir anda yok olurdu — "ease bitsin" isteği
        tam olarak bunun olmamasıdır.
    */
    const close = useCallback((returnFocus: boolean) => {
        setState((current) => (current === 'open' ? 'closing' : current));

        if (returnFocus) {
            triggerRef.current?.focus();
        }
    }, []);

    useEffect(() => {
        if (state !== 'closing') {
            return;
        }

        const panel = panelRef.current;

        if (panel === null) {
            setState('closed');

            return;
        }

        const done = () => setState('closed');
        panel.addEventListener('transitionend', done, { once: true });

        /*
            Güvenlik ağı: azaltılmış harekette süre neredeyse sıfıra iner ve
            bazı tarayıcılar o geçiş için olay üretmez. Olay hiç gelmezse
            panel sonsuza kadar "kapanıyor" hâlinde kalırdı.
        */
        const fallback = window.setTimeout(done, 400);

        return () => {
            panel.removeEventListener('transitionend', done);
            window.clearTimeout(fallback);
        };
    }, [state]);

    // Dışarı tıklama ve Escape — menü kendi kapanışını bilir.
    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: PointerEvent): void {
            if (!rootRef.current?.contains(event.target as Node)) {
                close(false);
            }
        }

        function onKeyDown(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                event.stopPropagation();
                close(true);
            }
        }

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [close, open]);

    /*
        Ok tuşlarıyla gezinme. `menu` rolünü verip bunu yapmamak, ekran okuyucu
        kullanan birine olmayan bir sözleşme vaat etmek olurdu.
    */
    function onPanelKeyDown(event: React.KeyboardEvent<HTMLDivElement>): void {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
            return;
        }

        const rows = Array.from(
            panelRef.current?.querySelectorAll<HTMLButtonElement>(
                '[role="menuitem"]:not(:disabled),[role="menuitemradio"]:not(:disabled)',
            ) ?? [],
        );

        if (rows.length === 0) {
            return;
        }

        event.preventDefault();
        const at = rows.indexOf(document.activeElement as HTMLButtonElement);
        const next = event.key === 'ArrowDown' ? at + 1 : at - 1;
        rows[(next + rows.length) % rows.length]?.focus();
    }

    function select(run: () => void): void {
        run();
        close(true);
    }

    const attachedEdge = placement === 'down' ? 'rounded-b-none' : 'rounded-t-none';

    return (
        <div ref={rootRef} className={clsx('relative w-full', className)}>
            <button
                ref={triggerRef}
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={mounted ? panelId : undefined}
                aria-label={label}
                onClick={() => (open ? close(false) : setState('open'))}
                className={clsx(
                    'flex min-h-[var(--density-hit-area-min)] w-full items-center gap-[var(--space-2)]',
                    'rounded-[var(--radius-md)] border border-border bg-[var(--color-surface-subtle)]',
                    'px-[var(--space-3)] py-[var(--space-2)] text-start',
                    'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-inout)]',
                    'hover:bg-surface-hover',
                    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                    // Açıkken panelle BİRLEŞİR: aradaki köşe yuvarlaklığı
                    // düşer ve iki kutu tek bir yüzey gibi okunur.
                    mounted && attachedEdge,
                )}
            >
                <span className="min-w-0 flex-1">{triggerContent}</span>
                <CaretDown
                    aria-hidden="true"
                    size={16}
                    weight="bold"
                    className={clsx(
                        'shrink-0 text-fg-muted transition-transform duration-[var(--duration-base)] ease-[var(--easing-inout)]',
                        // Kapalıyken ok, panelin AÇILACAĞI yönü gösterir;
                        // açıkken ters döner. Kullanıcı oka bakıp panelin
                        // nereden geleceğini bilir.
                        placement === 'up' ? 'rotate-180' : 'rotate-0',
                        open && (placement === 'up' ? 'rotate-0' : 'rotate-180'),
                    )}
                />
            </button>

            {mounted ? (
                <div
                    ref={panelRef}
                    id={panelId}
                    role="menu"
                    aria-label={label}
                    onKeyDown={onPanelKeyDown}
                    className={clsx(
                        'absolute inset-x-0 z-30 overflow-hidden border border-border bg-surface',
                        // Gölge YOK: panel tetikleyiciye yapışık ve kenarlıklı,
                        // yani zaten tek bir yüzey olarak okunuyor. Sahibin
                        // istediği yön düz (flat 2.0); yüzen bir gölge onu
                        // ekrandan koparıp 2013'e geri götürürdü.
                        'p-[var(--space-1)]',
                        'transition-[opacity,transform] duration-[var(--duration-base)] ease-[var(--easing-inout)]',
                        placement === 'down'
                            ? 'top-full origin-top rounded-b-[var(--radius-md)] border-t-0'
                            : 'bottom-full origin-bottom rounded-t-[var(--radius-md)] border-b-0',
                        open
                            ? 'translate-y-0 opacity-100'
                            : clsx(
                                  'pointer-events-none opacity-0',
                                  placement === 'down' ? '-translate-y-1' : 'translate-y-1',
                              ),
                    )}
                >
                    {header ? (
                        <div className="border-b border-border px-[var(--space-3)] py-[var(--space-3)]">
                            {header}
                        </div>
                    ) : null}

                    {radioGroup ? (
                        <>
                            <div className="px-[var(--space-3)] pt-[var(--space-3)] pb-[var(--space-1)] text-caption font-semibold uppercase tracking-[0.08em] text-fg-muted">
                                {radioGroup.label}
                            </div>
                            {radioGroup.options.map((option) => (
                                <MenuItemRadio
                                    key={option.key}
                                    checked={radioGroup.value === option.key}
                                    onSelect={() => select(() => radioGroup.onSelect(option.key))}
                                >
                                    {option.label}
                                </MenuItemRadio>
                            ))}
                            <div className="my-[var(--space-1)] border-t border-border" />
                        </>
                    ) : null}

                    {items.map((item) =>
                        item.selected === undefined ? (
                            <MenuItem
                                key={item.key}
                                icon={item.icon}
                                disabled={item.disabled}
                                destructive={item.destructive}
                                onSelect={() => select(item.onSelect)}
                            >
                                {item.label}
                            </MenuItem>
                        ) : (
                            /*
                                İçinde bulunduğumuz çalışma alanı bir EYLEM
                                değil, bir DURUMDUR: `menuitemradio` onu
                                "seçili" olarak duyurur, düz bir madde ise
                                hangisinde olduğumuzu hiç söylemezdi.
                            */
                            <MenuItemRadio
                                key={item.key}
                                checked={item.selected}
                                disabled={item.disabled}
                                onSelect={() => select(item.onSelect)}
                            >
                                {item.label}
                            </MenuItemRadio>
                        ),
                    )}
                </div>
            ) : null}
        </div>
    );
}

export default SidebarMenu;
