import { useState, type FormEvent } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';

type AccountSettingsRegionProps = {
    /** Sunucudan gelen mevcut ad; boşsa alan boş açılır. */
    currentName?: string;
};

type Outcome = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Kullanıcı kendi hesabını kendi onarır — `docs/83` (P1-07).
 *
 * Self-service bir üründe yanlış yazılmış bir ad ya da paylaşılmış bir şifre
 * için destek talebi açmak zorunda kalmak, ürünün "kendi kendine yeter"
 * iddiasını her gün çürütür.
 */
export function AccountSettingsRegion({ currentName = '' }: AccountSettingsRegionProps) {
    const [name, setName] = useState(currentName);
    const [nameState, setNameState] = useState<Outcome>('idle');
    const [nameError, setNameError] = useState<string | null>(null);

    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [passwordState, setPasswordState] = useState<Outcome>('idle');
    const [passwordError, setPasswordError] = useState<string | null>(null);

    async function saveName(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setNameState('saving');
        setNameError(null);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch('/api/user/profile', {
                ...buildAuthRequestInit({ method: 'PUT' }),
                credentials: 'same-origin',
                body: JSON.stringify({ name }),
            });

            if (!response.ok) {
                setNameState('error');
                setNameError(t('workspace.settings.account.name.error'));

                return;
            }

            setNameState('saved');
        } catch {
            setNameState('error');
            setNameError(t('workspace.settings.account.name.error'));
        }
    }

    async function savePassword(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setPasswordState('saving');
        setPasswordError(null);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch('/api/user/password', {
                ...buildAuthRequestInit({ method: 'PUT' }),
                credentials: 'same-origin',
                body: JSON.stringify({
                    currentPassword,
                    password,
                    password_confirmation: passwordConfirmation,
                }),
            });

            if (!response.ok) {
                setPasswordState('error');
                setPasswordError(t('workspace.settings.account.password.error'));

                return;
            }

            setPasswordState('saved');
            // Alanlar TEMİZLENİR: ekranda duran bir şifre, omuz üstünden
            // okunabilecek bir şifredir.
            setCurrentPassword('');
            setPassword('');
            setPasswordConfirmation('');
        } catch {
            setPasswordState('error');
            setPasswordError(t('workspace.settings.account.password.error'));
        }
    }

    const fieldClass =
        'w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-fg';
    const labelClass = 'block text-body font-medium text-fg-secondary';
    const buttonClass =
        'self-start rounded-md border border-border px-3 py-2 text-body font-medium text-fg';

    return (
        <section
            aria-label={t('workspace.settings.account.region')}
            className="flex flex-col gap-6"
        >
            <form className="flex flex-col gap-2" onSubmit={saveName} noValidate>
                <label className={labelClass} htmlFor="account-name">
                    {t('workspace.settings.account.name.label')}
                </label>
                <input
                    id="account-name"
                    name="account-name"
                    type="text"
                    className={fieldClass}
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                />
                <button type="submit" className={buttonClass} disabled={nameState === 'saving'}>
                    {t('workspace.settings.account.name.submit')}
                </button>
                {nameError ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {nameError}
                    </p>
                ) : null}
                {nameState === 'saved' ? (
                    <p role="status" className="text-body text-fg-success">
                        {t('workspace.settings.account.name.saved')}
                    </p>
                ) : null}
            </form>

            <form className="flex flex-col gap-2" onSubmit={savePassword} noValidate>
                <h3 className="text-body font-semibold text-fg">
                    {t('workspace.settings.account.password.title')}
                </h3>
                {/*
                    Diğer oturumların kapanacağı ÖNCEDEN söylenir: sürpriz bir
                    çıkış, kullanıcıya ürünün bozulduğunu düşündürür.
                */}
                <p className="text-meta text-fg-muted">
                    {t('workspace.settings.account.password.help')}
                </p>

                <label className={labelClass} htmlFor="account-current-password">
                    {t('workspace.settings.account.password.current')}
                </label>
                <input
                    id="account-current-password"
                    name="account-current-password"
                    type="password"
                    autoComplete="current-password"
                    className={fieldClass}
                    value={currentPassword}
                    onChange={(event) => setCurrentPassword(event.target.value)}
                />

                <label className={labelClass} htmlFor="account-new-password">
                    {t('workspace.settings.account.password.next')}
                </label>
                <input
                    id="account-new-password"
                    name="account-new-password"
                    type="password"
                    autoComplete="new-password"
                    className={fieldClass}
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                />

                <label className={labelClass} htmlFor="account-confirm-password">
                    {t('workspace.settings.account.password.confirm')}
                </label>
                <input
                    id="account-confirm-password"
                    name="account-confirm-password"
                    type="password"
                    autoComplete="new-password"
                    className={fieldClass}
                    value={passwordConfirmation}
                    onChange={(event) => setPasswordConfirmation(event.target.value)}
                />

                <button type="submit" className={buttonClass} disabled={passwordState === 'saving'}>
                    {t('workspace.settings.account.password.submit')}
                </button>

                {passwordError ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {passwordError}
                    </p>
                ) : null}
                {passwordState === 'saved' ? (
                    <p role="status" className="text-body text-fg-success">
                        {t('workspace.settings.account.password.saved')}
                    </p>
                ) : null}
            </form>
        </section>
    );
}

export default AccountSettingsRegion;
