import { useEffect, useRef, type KeyboardEvent } from 'react';

import { t } from '../../../../i18n/platform';
import { formatPlanPrice, type Plan } from './PlanList';

type PlanActivationDialogProps = {
    plan: Plan;
    onCancel: () => void;
    onConfirm: () => void;
    confirming: boolean;
};

function getFocusableElements(container: HTMLElement): HTMLElement[] {
    return Array.from(container.querySelectorAll<HTMLElement>('button:not([disabled])'));
}

export function PlanActivationDialog({
    plan,
    onCancel,
    onConfirm,
    confirming,
}: PlanActivationDialogProps) {
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
            aria-label={t('platform.plans.activate.dialog.heading')}
            className="flex flex-col gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700"
            onKeyDown={handleKeyDown}
        >
            <h2 className="text-sm font-semibold text-fg">
                {t('platform.plans.activate.dialog.heading')}
            </h2>
            <span className="font-medium text-fg">{plan.name}</span>
            <span className="text-fg-muted">{plan.code}</span>
            <span className="text-fg-secondary">{formatPlanPrice(plan)}</span>

            <div className="flex gap-2">
                <button
                    type="button"
                    className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300"
                    onClick={onCancel}
                >
                    {t('platform.plans.activate.dialog.cancel')}
                </button>
                <button
                    type="button"
                    disabled={confirming}
                    className="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50 dark:bg-white dark:text-gray-900"
                    onClick={onConfirm}
                >
                    {t('platform.plans.activate.dialog.confirm')}
                </button>
            </div>
        </div>
    );
}

export default PlanActivationDialog;
