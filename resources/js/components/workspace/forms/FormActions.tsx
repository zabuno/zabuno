import { Button } from 'flowbite-react';

type FormActionsProps = {
    error: string;
    saving: boolean;
    saveLabel: string;
    cancelLabel: string;
    onCancel: () => void;
};

/**
 * Micro: the error alert + Save/Cancel row shared by every Brand/Location
 * edit form. Save stays a submit button so the owning <form>'s onSubmit
 * still fires; disabled state during saving is the only status this
 * component derives, straight from the `saving` prop.
 */
export function FormActions({ error, saving, saveLabel, cancelLabel, onCancel }: FormActionsProps) {
    return (
        <>
            {error && (
                <p role="alert" className="text-sm font-medium text-red-600 dark:text-red-400">
                    {error}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={saving}>
                    {saveLabel}
                </Button>
                <Button type="button" color="light" onClick={onCancel} disabled={saving}>
                    {cancelLabel}
                </Button>
            </div>
        </>
    );
}

export default FormActions;
