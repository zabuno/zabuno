import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { AccountSettingsRegion } from './AccountSettingsRegion';

/**
 * KİŞİSEL BİLGİLER — `docs/83` (P1-07) + kanonik kaynak (`panel.dc.html` >
 * "Profil").
 *
 * Self-service bir üründe kullanıcı kendi hesabını kendi onarır. Yanlış
 * yazılmış bir ad ya da paylaşılmış bir şifre için destek talebi açmak
 * zorunda kalmak, ürünün "kendi kendine yeter" iddiasını her gün çürütür.
 *
 * BÖLÜM AYARLAR'DAN PROFİL'E TAŞINDI (docs/109). Kaynakta ad, e-posta ve
 * şifre Profil ekranındadır; Ayarlar çalışma alanına aittir. Depoda aynı
 * form iki ekranda birden duruyordu — bir ayarın tek bir evi olur.
 */
function jsonResponse(status: number): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => ({}),
    } as Response;
}

function mount(status = 200) {
    const calls: { url: string; method: string; body: unknown }[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            calls.push({
                url: String(url),
                method,
                body: init?.body ? JSON.parse(String(init.body)) : null,
            });

            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204);

            return jsonResponse(status);
        }),
    );

    render(<AccountSettingsRegion currentName="Ismail" email="ismail@pasadoner.com" />);

    return { calls, user: userEvent.setup() };
}

describe('kişisel bilgiler (docs/83, docs/109 — Profil)', () => {
    it('kullanıcı kendi adını düzeltir', async () => {
        const { calls, user } = mount();

        const field = screen.getByLabelText('Your name');
        await user.clear(field);
        await user.type(field, 'İsmail Karaca');
        await user.click(screen.getByRole('button', { name: 'Save name' }));

        await waitFor(() => {
            expect(calls.some((call) => call.url === '/api/user/profile')).toBe(true);
        });

        const put = calls.find((call) => call.url === '/api/user/profile')!;
        expect(put.method).toBe('PUT');
        expect(put.body).toEqual({ name: 'İsmail Karaca' });
        expect(await screen.findByText('Your name was saved.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    /*
        E-POSTA GÖRÜNÜR AMA DÜZENLENMEZ. Kaynak "Kişisel bilgiler" bloğunda
        adın yanında e-postayı da gösteriyor. Depoda hiç görünmüyordu:
        iki hesabı olan biri hangi hesapla girdiğini ekranda okuyamıyordu.
        Düzenlenebilir YAPILMAZ — e-posta değişimi doğrulama akışı ister ve
        o akış üründe yok; olmayan bir akışı taklit eden bir alan, kaydeder
        gibi yapıp hiçbir şey yapmazdı.
    */
    it('e-postayı gösterir ama düzenlettirmez', () => {
        mount();

        const email = screen.getByLabelText('Email') as HTMLInputElement;
        expect(email.value).toBe('ismail@pasadoner.com');
        expect(email.readOnly).toBe(true);

        vi.unstubAllGlobals();
    });

    /*
        ŞİFRE AÇILIR BÖLÜMÜN İÇİNDE (kaynağın `<details>` düğümü). Üç şifre
        alanı her açılışta ekranda durunca, yılda bir kez yapılan bir iş
        her gün yapılan işlerin (ad düzeltme, tema) önüne geçiyordu. Kapalı
        bir bölüm, kullanıcıya "bu senin şu anki işin değil" der.
    */
    it('şifre alanları varsayılan olarak KAPALI bir açılır bölümdedir', () => {
        mount();

        const disclosure = screen.getByText('Change password').closest('details');
        expect(disclosure).not.toBeNull();
        expect((disclosure as HTMLDetailsElement).open).toBe(false);

        vi.unstubAllGlobals();
    });

    it('şifre değişikliği mevcut şifreyi de gönderir ve alanları temizler', async () => {
        const { calls, user } = mount();

        await user.click(screen.getByText('Change password'));

        await user.type(screen.getByLabelText('Current password'), 'eski-parola-123');
        await user.type(screen.getByLabelText('New password'), 'yeni-parola-456');
        await user.type(screen.getByLabelText('Repeat new password'), 'yeni-parola-456');
        await user.click(screen.getByRole('button', { name: 'Save new password' }));

        await waitFor(() => {
            expect(calls.some((call) => call.url === '/api/user/password')).toBe(true);
        });

        expect(calls.find((call) => call.url === '/api/user/password')!.body).toEqual({
            currentPassword: 'eski-parola-123',
            password: 'yeni-parola-456',
            password_confirmation: 'yeni-parola-456',
        });

        // Ekranda duran bir şifre, omuz üstünden okunabilecek bir şifredir.
        await waitFor(() => {
            expect(screen.getByLabelText('Current password')).toHaveValue('');
        });
        expect(screen.getByLabelText('New password')).toHaveValue('');

        vi.unstubAllGlobals();
    });

    /*
        BU CÜMLE GERÇEĞİ ANLATIR, süs değildir: `UpdatePasswordController`
        şifre değişince kullanıcının diğer oturumlarını `sessions`
        tablosundan siler ve bunu `ACCOUNT-PASSWORD-OTHER-SESSIONS-01`
        kanıtlar. Sürpriz bir çıkış, kullanıcıya ürünün bozulduğunu
        düşündürür.
    */
    it('diğer cihazlardaki oturumların kapanacağı önceden söylenir', async () => {
        const { user } = mount();

        await user.click(screen.getByText('Change password'));

        expect(
            screen.getByText(
                'Changing your password signs you out on your other devices. This one stays signed in.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('başarısızlık sessiz kalmaz', async () => {
        const { user } = mount(422);

        await user.click(screen.getByText('Change password'));

        await user.type(screen.getByLabelText('Current password'), 'yanlis');
        await user.type(screen.getByLabelText('New password'), 'yeni-parola-456');
        await user.type(screen.getByLabelText('Repeat new password'), 'yeni-parola-456');
        await user.click(screen.getByRole('button', { name: 'Save new password' }));

        expect(
            await screen.findByText(
                'Your password could not be changed. Check your current password and try again.',
            ),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
