import { useCallback, useId, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import type { BrandProfile } from '../../BrandEditForm';

type BrandColorsRegionProps = {
    workspaceId: number;
    brand: BrandProfile;
    onSaved: (brand: BrandProfile) => void;
};

/** Boş dize = "renk seçilmedi"; sunucuda `null` olur. */
const EMPTY = '';

/**
 * Marka renkleri — sahibin isteği (2026-09-04): "restoran yöneticisi olarak,
 * marka renklerimi (primary color, secondary color) değiştirebilmeliyim".
 *
 * Renk seçici İKİ girdiyle çalışır: bir renk kutusu ve onun yanında altı
 * haneli kod alanı. Tek başına renk kutusu, kurumsal kimliği dosyada `#C8102E`
 * olarak yazılı olan bir restoran sahibi için işe yaramaz — o kodu YAZMAK
 * ister. Tek başına kod alanı ise rengi hiç bilmeyeni yalnız bırakır.
 *
 * Renkler markanın kendi kaydında durur, kişisel temada değil: aynı restoranın
 * ikinci yöneticisi girdiğinde de menü aynı renkte görünmelidir. Tema (açık /
 * koyu) ise kişiseldir ve bu ekranın ayrı bir bölümünde durur.
 */
export function BrandColorsRegion({ workspaceId, brand, onSaved }: BrandColorsRegionProps) {
    const primaryId = useId();
    const secondaryId = useId();

    const [primary, setPrimary] = useState(brand.primary_color ?? EMPTY);
    const [secondary, setSecondary] = useState(brand.secondary_color ?? EMPTY);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const dirty =
        (primary || null) !== (brand.primary_color ?? null) ||
        (secondary || null) !== (brand.secondary_color ?? null);

    const submit = useCallback(async () => {
        setSaving(true);
        setSaved(false);
        setError(null);

        try {
            await bootstrapCsrfCookie();
            const response = await fetch(
                `/api/workspaces/${workspaceId}/brand`,
                buildAuthRequestInit({
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    /*
                        Ad, saat dilimi ve para birimi zorunlu alanlar; renk
                        kaydederken de gönderilirler. Aksi hâlde sunucu
                        "eksik alan" derdi ve kullanıcı, hiç dokunmadığı bir
                        alan yüzünden rengini kaydedemezdi.
                    */
                    body: JSON.stringify({
                        name: brand.name,
                        timezone: brand.timezone,
                        currency: brand.currency,
                        primary_color: primary === EMPTY ? null : primary,
                        secondary_color: secondary === EMPTY ? null : secondary,
                    }),
                }),
            );

            if (!response.ok) {
                setError(t('workspace.profile.colors.error'));
                return;
            }

            onSaved((await response.json()) as BrandProfile);
            setSaved(true);
        } catch {
            setError(t('workspace.profile.colors.error'));
        } finally {
            setSaving(false);
        }
    }, [brand.currency, brand.name, brand.timezone, onSaved, primary, secondary, workspaceId]);

    const field = (
        id: string,
        labelKey: 'workspace.profile.colors.primary' | 'workspace.profile.colors.secondary',
        value: string,
        setValue: (next: string) => void,
    ) => (
        <div className="flex flex-col gap-[var(--space-2)]">
            <label htmlFor={id} className="text-body font-medium text-fg">
                {t(labelKey)}
            </label>
            <div className="flex items-center gap-[var(--space-2)]">
                {/*
                    Renk kutusu ile kod alanı AYNI değeri paylaşır; hangisinden
                    değiştirilirse diğeri onu gösterir. İkisinin ayrışması,
                    kullanıcının ekranda gördüğü renkle kaydedilen rengin farklı
                    olması demek olurdu.
                */}
                <input
                    type="color"
                    aria-label={t(labelKey)}
                    value={value === EMPTY ? '#000000' : value}
                    onChange={(event) => setValue(event.target.value)}
                    className="h-[var(--density-hit-area-min)] w-[3rem] shrink-0 rounded-md border border-border bg-surface p-1"
                />
                <input
                    id={id}
                    type="text"
                    inputMode="text"
                    spellCheck={false}
                    placeholder="#C8102E"
                    value={value}
                    onChange={(event) => setValue(event.target.value.trim())}
                    className="min-h-[var(--density-hit-area-min)] w-[10ch] rounded-md border border-border bg-surface px-3 py-2 text-body text-fg"
                />
                {value !== EMPTY ? (
                    <button
                        type="button"
                        className="min-h-[var(--density-hit-area-min)] rounded-md px-2 py-1 text-meta text-fg-secondary underline hover:bg-surface-hover"
                        onClick={() => setValue(EMPTY)}
                    >
                        {t('workspace.profile.colors.clear')}
                    </button>
                ) : null}
            </div>
        </div>
    );

    return (
        <section
            aria-labelledby="profile-colors-heading"
            className="flex flex-col gap-[var(--space-3)]"
        >
            <h3 id="profile-colors-heading" className="text-body font-bold text-fg">
                {t('workspace.profile.colors.heading')}
            </h3>
            <p className="text-body text-fg-secondary">{t('workspace.profile.colors.help')}</p>

            <form
                noValidate
                className="flex flex-col gap-[var(--space-3)]"
                onSubmit={(event) => {
                    event.preventDefault();
                    void submit();
                }}
            >
                {field(primaryId, 'workspace.profile.colors.primary', primary, setPrimary)}
                {field(secondaryId, 'workspace.profile.colors.secondary', secondary, setSecondary)}

                <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                    <button
                        type="submit"
                        className="min-h-[var(--density-hit-area-min)] rounded-md border border-action bg-action px-4 py-2 text-body font-bold text-action-fg disabled:border-border disabled:bg-[var(--color-surface-subtle)] disabled:text-fg-muted"
                        disabled={saving || !dirty}
                    >
                        {saving
                            ? t('workspace.profile.colors.saving')
                            : t('workspace.profile.colors.save')}
                    </button>
                    {saved ? (
                        <span role="status" className="text-body text-fg-secondary">
                            {t('workspace.profile.colors.saved')}
                        </span>
                    ) : null}
                </div>
            </form>

            {error !== null ? (
                <p role="alert" className="text-body text-fg-danger">
                    {error}
                </p>
            ) : null}
        </section>
    );
}

export default BrandColorsRegion;
