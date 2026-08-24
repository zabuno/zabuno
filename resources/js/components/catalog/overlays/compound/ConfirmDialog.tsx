import type { ReactNode } from 'react';
import { useEffect, useId } from 'react';
import {
    Modal,
    ModalBody,
    ModalFooter,
    Button as FlowbiteButton,
    Spinner,
    useModalContext,
} from 'flowbite-react';
import { CloseButton } from '../micro/CloseButton';

/**
 * Registers this dialog's title id with Flowbite's ModalContext so the
 * Modal's floating element carries a correct `aria-labelledby` — mirrors
 * what ModalHeader does internally, without pulling in ModalHeader's own
 * built-in close button (this dialog uses Micro/Overlays/CloseButton instead).
 */
function ConfirmDialogTitle({ id, children }: { id: string; children: ReactNode }) {
    const { setHeaderId } = useModalContext();

    useEffect(() => {
        setHeaderId(id);
        return () => setHeaderId(undefined);
    }, [id, setHeaderId]);

    return (
        <h2 id={id} className="text-lg font-semibold text-gray-900 dark:text-white">
            {children}
        </h2>
    );
}

export type ConfirmDialogProps = {
    /** Controlled open state — ConfirmDialog renders nothing when false. */
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    children?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    /**
     * Marks the confirmed action as destructive/irreversible (delete, revoke
     * access, etc.) — the confirm button switches to the failure color so
     * its visual weight matches the risk, distinct from an ordinary confirm.
     */
    destructive?: boolean;
    confirmLoading?: boolean;
    className?: string;
};

/**
 * Compound: composes Flowbite's Modal (which owns the floating-ui focus
 * trap, Escape handling, and focus-return to the trigger on close) with
 * Micro/Overlays/CloseButton for the corner dismiss control. Does not
 * reimplement Modal's overlay/backdrop/focus-trap markup.
 */
export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    children,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    destructive = false,
    confirmLoading = false,
    className,
}: ConfirmDialogProps) {
    const titleId = useId();
    const guardedClose = () => {
        if (confirmLoading) return;
        onClose();
    };

    return (
        <Modal
            show={open}
            onClose={guardedClose}
            dismissible={!confirmLoading}
            popup
            className={className}
        >
            <div className="flex items-start justify-between p-4">
                <ConfirmDialogTitle id={titleId}>{title}</ConfirmDialogTitle>
                <CloseButton onClick={guardedClose} disabled={confirmLoading} />
            </div>
            <ModalBody>{children}</ModalBody>
            <ModalFooter>
                <FlowbiteButton
                    color={destructive ? 'failure' : 'blue'}
                    onClick={onConfirm}
                    disabled={confirmLoading}
                    aria-busy={confirmLoading || undefined}
                >
                    {confirmLoading ? (
                        <span className="flex items-center gap-2">
                            <Spinner size="sm" aria-hidden="true" />
                            <span>{confirmLabel}</span>
                        </span>
                    ) : (
                        confirmLabel
                    )}
                </FlowbiteButton>
                <FlowbiteButton color="gray" onClick={guardedClose} disabled={confirmLoading}>
                    {cancelLabel}
                </FlowbiteButton>
            </ModalFooter>
        </Modal>
    );
}
