import { useId, useState, type FormEvent } from 'react';
import { t } from '../../../../i18n/workspace';

const SLOT_OPTIONS = [
    ['hero', 'workspace.media.upload.field.assetSlot.hero'],
    ['cards', 'workspace.media.upload.field.assetSlot.cards'],
    ['pricing', 'workspace.media.upload.field.assetSlot.pricing'],
    ['features', 'workspace.media.upload.field.assetSlot.features'],
    ['testimonial', 'workspace.media.upload.field.assetSlot.testimonial'],
    ['avatar', 'workspace.media.upload.field.assetSlot.avatar'],
    ['logo', 'workspace.media.upload.field.assetSlot.logo'],
    ['cover', 'workspace.media.upload.field.assetSlot.cover'],
    ['favicon', 'workspace.media.upload.field.assetSlot.favicon'],
    ['ogImage', 'workspace.media.upload.field.assetSlot.ogImage'],
    ['appIcon', 'workspace.media.upload.field.assetSlot.appIcon'],
    ['profileAvatar', 'workspace.media.upload.field.assetSlot.profileAvatar'],
    ['categoryHero', 'workspace.media.upload.field.assetSlot.categoryHero'],
    ['itemImage', 'workspace.media.upload.field.assetSlot.itemImage'],
    ['gallery', 'workspace.media.upload.field.assetSlot.gallery'],
    ['printLogo', 'workspace.media.upload.field.assetSlot.printLogo'],
    ['emailHeader', 'workspace.media.upload.field.assetSlot.emailHeader'],
] as const;

type MediaUploadRegionProps = {
    onSubmit: (formData: FormData) => Promise<void> | void;
};

/**
 * File, alt text, and asset slot are the real first-API-contract fields —
 * rights/license and expiry stay disabled since they are outside this
 * bounded intake slice (MEDIA-INTAKE-UPLOAD-01/REQUIRED-FIELDS-01).
 */
export function MediaUploadRegion({ onSubmit }: MediaUploadRegionProps) {
    const fileId = useId();
    const altId = useId();
    const slotId = useId();
    const rightsId = useId();
    const expiryId = useId();

    const [file, setFile] = useState<File | null>(null);
    const [altText, setAltText] = useState('');
    const [slot, setSlot] = useState('');
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!file || altText.trim() === '' || slot === '') {
            return;
        }

        const formData = new FormData();
        formData.set('file', file);
        formData.set('altText', altText);
        formData.set('slot', slot);

        setSubmitting(true);

        try {
            await onSubmit(formData);
            setFile(null);
            setAltText('');
            setSlot('');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <form
            role="region"
            aria-label={t('workspace.media.upload.region')}
            onSubmit={(event) => void handleSubmit(event)}
            className="flex flex-col gap-3"
        >
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('workspace.media.upload.heading')}
            </h3>

            <div className="flex flex-col gap-3">
                <label htmlFor={fileId} className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('workspace.media.upload.field.file')}
                    <input
                        id={fileId}
                        type="file"
                        className="text-sm text-gray-700 dark:text-gray-300"
                        onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                    />
                </label>

                <label htmlFor={altId} className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('workspace.media.upload.field.altText')}
                    <input
                        id={altId}
                        type="text"
                        required
                        value={altText}
                        onChange={(event) => setAltText(event.target.value)}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white"
                    />
                </label>

                <label htmlFor={slotId} className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('workspace.media.upload.field.assetSlot')}
                    <select
                        id={slotId}
                        value={slot}
                        onChange={(event) => setSlot(event.target.value)}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:text-white"
                    >
                        <option value="">{t('workspace.media.upload.field.assetSlot.placeholder')}</option>
                        {SLOT_OPTIONS.map(([value, labelKey]) => (
                            <option key={value} value={value}>
                                {t(labelKey)}
                            </option>
                        ))}
                    </select>
                </label>

                <label htmlFor={rightsId} className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('workspace.media.upload.field.rights')}
                    <input
                        id={rightsId}
                        type="text"
                        disabled
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-400 dark:border-gray-600"
                    />
                </label>

                <label htmlFor={expiryId} className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                    {t('workspace.media.upload.field.expiry')}
                    <input
                        id={expiryId}
                        type="date"
                        disabled
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-400 dark:border-gray-600"
                    />
                </label>
            </div>

            <button
                type="submit"
                disabled={submitting}
                className="self-start rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300"
            >
                {t('workspace.media.upload.button')}
            </button>

            <p className="text-xs text-gray-500 dark:text-gray-400">
                {t('workspace.media.security.explanation')}
            </p>
        </form>
    );
}

export default MediaUploadRegion;
