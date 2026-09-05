import { useState, type FormEvent } from 'react';

import { t } from '../../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';

type AccountSettingsRegionProps = {
    /** Sunucudan gelen mevcut ad; boşsa alan boş açılır. */
    currentName?: string;
    /** Oturumdaki e-posta. Gösterilir, düzenlenmez. */
    email: string;
};

type Outcome = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Kişisel bilgiler — `docs/83` (P1-07) + kanonik kaynak (`panel.dc.html` >
 * "Profil" > "Kişisel bilgiler").
 *
 * Self-service bir üründe yanlış yazılmış bir ad ya da paylaşılmış bir şifre
 * için destek talebi açmak zorunda kalmak, ürünün "kendi kendine yeter"
 * iddiasını her gün çürütür.
 *
 * BÖLÜM ARTIK YALNIZ PROFİL'DEDİR (docs/109). Aynı form hem Ayarlar >
 * Hesap sekmesinde hem Profil ekranında çiziliyordu: bir kullanıcı adını
 * Ayarlar'dan değiştirdiğinde, "acaba bu yalnız bu restoranda mı değişti?"
 * diye sormakta haklıydı. Kaynak sınırı net çiziyor — Ayarlar çalışma
 * alanına, Profil kişiye aittir.
 *
 * ŞİFRE AÇILIR BÖLÜMÜN İÇİNDE (kaynağın `<details>` düğümü). Üç şifre alanı
 * her açılışta ekranda dururken, yılda bir kez yapılan bir iş her gün
 * yapılanların (ad düzeltme, tema) önüne geçiyordu.
 */
export function AccountSettingsRegion({ currentName = '', email }: AccountSettingsRegionProps) {
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
                setNameError(t('workspace.profile.account.name.error'));

                return;
            }

            setNameState('saved');
        } catch {
            setNameState('error');
            setNameError(t('workspace.profile.account.name.error'));
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
                setPasswordError(t('workspace.profile.account.password.error'));

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
            setPasswordError(t('workspace.profile.account.password.error'));
        }
    }

    const fieldClass =
        'w-full min-h-[var(--control-height)] rounded-md border border-border bg-surface px-[var(--space-3)] py-[var(--space-2)] text-body text-fg';
    const readOnlyFieldClass = `${fieldClass} bg-[var(--color-surface-subtle)] text-fg-secondary`;
    const labelClass = 'block text-body font-medium text-fg-secondary';
    const buttonClass =
        'self-start min-h-[var(--control-height)] rounded-md border border-border px-[var(--space-3)] py-[var(--space-2)] text-body font-medium text-fg';

    return (
        <section
            aria-label={t('workspace.profile.account.region')}
            className="flex flex-col gap-[var(--space-5)]"
        >
            <form className="flex flex-col gap-[var(--space-2)]" onSubmit={saveName} noValidate>
                <label className={labelClass} htmlFor="account-name">
                    {t('workspace.profile.account.name.label')}
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
                    {t('workspace.profile.account.name.submit')}
                </button>
                {nameError ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {nameError}
                    </p>
                ) : null}
                {nameState === 'saved' ? (
                    <p role="status" className="text-body text-fg-success">
                        {t('workspace.profile.account.name.saved')}
                    </p>
                ) : null}
            </form>

            {/*
                E-POSTA GÖRÜNÜR AMA DÜZENLENMEZ. İki hesabı olan biri hangi
                hesapla girdiğini ekranda okuyabilmeli; ama e-posta değişimi
                doğrulama akışı ister ve o akış üründe yok. Düzenlenebilir bir
                alan, kaydeder gibi yapıp hiçbir şey yapmazdı.
            */}
            <div className="flex flex-col gap-[var(--space-2)]">
                <label className={labelClass} htmlFor="account-email">
                    {t('workspace.profile.account.email.label')}
                </label>
                <input
                    id="account-email"
                    name="account-email"
                    type="email"
                    readOnly
                    className={readOnlyFieldClass}
                    value={email}
                />
            </div>

            <details className="rounded-[var(--radius-lg)] border border-border p-[var(--space-3)]">
                <summary className="min-h-[var(--control-height)] cursor-pointer list-none text-body font-medium text-fg">
                    {t('workspace.profile.account.password.title')}
                </summary>

                <form
                    className="mt-[var(--space-3)] flex flex-col gap-[var(--space-2)]"
                    onSubmit={savePassword}
                    noValidate
                >
                    <label className={labelClass} htmlFor="account-current-password">
                        {t('workspace.profile.account.password.current')}
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
                        {t('workspace.profile.account.password.next')}
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
                        {t('workspace.profile.account.password.confirm')}
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

                    <button
                        type="submit"
                        className={buttonClass}
                        disabled={passwordState === 'saving'}
                    >
                        {t('workspace.profile.account.password.submit')}
                    </button>

                    {/*
                        Diğer cihazlardaki oturumların kapanacağı ÖNCEDEN
                        söylenir ve bu bir vaat değil, ölçülmüş davranıştır:
                        `UpdatePasswordController` o oturumları `sessions`
                        tablosundan siler.
                    */}
                    <p className="text-body text-fg-secondary">
                        {t('workspace.profile.account.password.help')}
                    </p>

                    {passwordError ? (
                        <p role="alert" className="text-body text-fg-danger">
                            {passwordError}
                        </p>
                    ) : null}
                    {passwordState === 'saved' ? (
                        <p role="status" className="text-body text-fg-success">
                            {t('workspace.profile.account.password.saved')}
                        </p>
                    ) : null}
                </form>
            </details>
        </section>
    );
}

export default AccountSettingsRegion;
