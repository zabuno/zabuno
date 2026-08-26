import { Button } from '../../../catalog/forms/micro/Button';
import { useEffect, useRef, type KeyboardEvent } from 'react';

import { t } from '../../../../i18n/platform';

type ManualPaymentConfirmDialogProps = {
    planLabel: string;
    endsAt: string;
    confirming: boolean;
    onCancel: () => void;
    onConfirm: () => void;
};

function getFocusableElements(container: HTMLElement): HTMLElement[] {
    return Array.from(container.querySelectorAll<HTMLElement>('button:not([disabled])'));
}

/**
 * A distinct human confirmation dialog: submitting the manual-payment form
 * never issues a request on its own — only this dialog's Confirm button
 * triggers the CSRF bootstrap and POST.
 */
export function ManualPaymentConfirmDialog({
    planLabel,
    endsAt,
    confirming,
    onCancel,
    onConfirm,
}: ManualPaymentConfirmDialogProps) {
    const dialogRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<Element | null>(null);

    useEffect(() => {
        triggerRef.current = document.activeElement;

        const dialog = dialogRef.current;
        if (dialog) {
            const [firstFocusable] = getFocusableElements(dialog);
            firstFocusable?.focus();
        }

        return () => {
            const trigger = triggerRef.current;
            if (trigger instanceof HTMLElement && document.contains(trigger)) {
                trigger.focus();
            }
        };
    }, []);

    function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        if (event.key === 'Escape') {
            event.preventDefault();
            onCancel();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const dialog = dialogRef.current;
        if (!dialog) {
            return;
        }

        const focusable = getFocusableElements(dialog);
        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey) {
            if (active === first || !dialog.contains(active)) {
                event.preventDefault();
                last.focus();
            }
        } else if (active === last || !dialog.contains(active)) {
            event.preventDefault();
            first.focus();
        }
    }

    return (
        <div
            ref={dialogRef}
            role="dialog"
            aria-modal="true"
            aria-label={t('platform.subscriptions.confirm.heading')}
            className="flex flex-col gap-3 rounded-lg border border-border p-4"
            onKeyDown={handleKeyDown}
        >
            <h2 className="text-sm font-semibold text-fg">
                {t('platform.subscriptions.confirm.heading')}
            </h2>
            <span className="text-fg-secondary">{planLabel}</span>
            <span className="text-fg-secondary">{endsAt}</span>

            <div className="flex gap-2">
                <Button color="light" type="button" onClick={onCancel}>
                    {t('platform.subscriptions.confirm.cancel')}
                </Button>
                <Button type="button" disabled={confirming} onClick={onConfirm}>
                    {t('platform.subscriptions.confirm.confirm')}
                </Button>
            </div>
        </div>
    );
}

export default ManualPaymentConfirmDialog;
