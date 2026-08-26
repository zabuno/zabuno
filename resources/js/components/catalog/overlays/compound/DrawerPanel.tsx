import type { KeyboardEvent, ReactNode } from 'react';
import { useEffect, useId, useRef } from 'react';
import { Drawer, DrawerItems } from 'flowbite-react';
import { CloseButton } from '../micro/CloseButton';

export type DrawerPanelProps = {
    /** Controlled open state — DrawerPanel renders nothing when false. */
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    position?: 'left' | 'right' | 'top' | 'bottom';
    className?: string;
};

/**
 * Compound: composes Flowbite's Drawer (which owns Escape handling and the
 * backdrop) with Micro/Overlays/CloseButton for the header dismiss control.
 * Drawer does not manage a focus trap or focus return on its own, so this
 * compound moves focus into the panel on open and restores the previously
 * focused element on close.
 */
export function DrawerPanel({
    open,
    onClose,
    title,
    children,
    position = 'right',
    className,
}: DrawerPanelProps) {
    const titleId = useId();
    const panelRef = useRef<HTMLDivElement>(null);
    const previouslyFocused = useRef<HTMLElement | null>(null);

    useEffect(() => {
        if (open) {
            previouslyFocused.current =
                document.activeElement instanceof HTMLElement ? document.activeElement : null;
            panelRef.current?.focus();
        } else if (previouslyFocused.current) {
            previouslyFocused.current.focus();
            previouslyFocused.current = null;
        }
    }, [open]);

    if (!open) {
        return null;
    }

    const getFocusableDescendants = (): HTMLElement[] => {
        const root = panelRef.current;
        if (!root) {
            return [];
        }
        const candidates = root.querySelectorAll<HTMLElement>(
            'a[href], button, input, select, textarea, [tabindex]',
        );
        const styleCache = new Map<HTMLElement, CSSStyleDeclaration>();
        const getStyle = (element: HTMLElement): CSSStyleDeclaration => {
            let style = styleCache.get(element);
            if (!style) {
                style = window.getComputedStyle(element);
                styleCache.set(element, style);
            }
            return style;
        };
        const isHiddenByAncestry = (element: HTMLElement): boolean => {
            let node: HTMLElement | null = element;
            while (node) {
                const style = getStyle(node);
                if (style.display === 'none' || style.visibility === 'hidden') {
                    return true;
                }
                if (node.getAttribute('aria-hidden') === 'true') {
                    return true;
                }
                if (node === root) {
                    break;
                }
                node = node.parentElement;
            }
            return false;
        };
        return Array.from(candidates).filter((element) => {
            if (
                element.hasAttribute('disabled') ||
                element.getAttribute('aria-hidden') === 'true'
            ) {
                return false;
            }
            if (element.tabIndex < 0) {
                return false;
            }
            if (element.hidden) {
                return false;
            }
            if (isHiddenByAncestry(element)) {
                return false;
            }
            return true;
        });
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = getFocusableDescendants();
        if (focusable.length === 0) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey) {
            if (active === first || !focusable.includes(active as HTMLElement)) {
                event.preventDefault();
                last.focus();
            }
        } else if (active === last || !focusable.includes(active as HTMLElement)) {
            event.preventDefault();
            first.focus();
        }
    };

    return (
        <Drawer
            ref={panelRef}
            open={open}
            onClose={onClose}
            position={position}
            aria-labelledby={titleId}
            aria-describedby={undefined}
            className={className}
            onKeyDown={handleKeyDown}
        >
            <div className="mb-4 flex items-center justify-between">
                <h2 id={titleId} className="text-base font-semibold text-fg">
                    {title}
                </h2>
                <CloseButton onClick={onClose} />
            </div>
            <DrawerItems>{children}</DrawerItems>
        </Drawer>
    );
}
