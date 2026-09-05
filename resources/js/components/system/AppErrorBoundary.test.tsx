import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { useState } from 'react';
import userEvent from '@testing-library/user-event';
import { AppErrorBoundary } from './AppErrorBoundary';

const trackEvent = vi.hoisted(() => vi.fn());
vi.mock('@/lib/analytics', () => ({ trackEvent }));

function Bomb({ explode }: { explode: boolean }): React.JSX.Element {
    if (explode) {
        // Gerçekte gözlenen çökme buydu: bir dizi beklenirken başka bir şey
        // gelince `.map` çağrılıyor ve tüm ağaç düşüyordu.
        throw new TypeError('i.map is not a function');
    }

    return <p>Menu screen</p>;
}

describe('AppErrorBoundary (docs/52)', () => {
    beforeEach(() => {
        // React yakalanan hatayı yine de konsola yazar; testin çıktısını
        // kirletmesin diye susturuluyor. Hatanın KENDİSİ bastırılmıyor.
        vi.spyOn(console, 'error').mockImplementation(() => {});
        trackEvent.mockClear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('hata yokken çocuklarını olduğu gibi gösterir', () => {
        render(
            <AppErrorBoundary>
                <Bomb explode={false} />
            </AppErrorBoundary>,
        );

        expect(screen.getByText('Menu screen')).toBeInTheDocument();
    });

    /**
     * Paketten önceki davranış buydu: tek bir render hatası `#app` div'ini
     * bomboş bırakıyordu. Boş sayfa kullanıcı için en kötü arıza biçimidir —
     * ne olduğunu, ne yapacağını söylemez; çoğu kullanıcı bunu "internetim
     * gitti" diye yorumlar ve hiç bildirmez.
     */
    it('çökmeyi boş ekran yerine kurtarılabilir bir yüzeye çevirir', () => {
        render(
            <AppErrorBoundary scope="route">
                <Bomb explode={true} />
            </AppErrorBoundary>,
        );

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /reload/i })).toBeInTheDocument();
        expect(screen.queryByText('Menu screen')).not.toBeInTheDocument();
    });

    /**
     * Kurtarmayı GERÇEK yapan davranış.
     *
     * React bir hata yakaladıktan sonra o ağacı kalıcı olarak bozuk sayar.
     * `resetKey` olmasaydı kullanıcı başka bir bölüme geçse bile hata ekranı
     * kalırdı — ve tek çıkış yolu sayfayı yenilemek olurdu, o da aynı bozuk
     * ekrana dönmek demektir.
     */
    it('rota değiştiğinde kendini sıfırlar ve yeni ekran çalışır', async () => {
        function Harness(): React.JSX.Element {
            const [section, setSection] = useState('menu');

            return (
                <>
                    <button type="button" onClick={() => setSection('analytics')}>
                        Go to analytics
                    </button>
                    <AppErrorBoundary scope="route" resetKey={section}>
                        {section === 'menu' ? <Bomb explode={true} /> : <p>Analytics screen</p>}
                    </AppErrorBoundary>
                </>
            );
        }

        render(<Harness />);

        expect(screen.getByRole('alert')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Go to analytics' }));

        expect(screen.getByText('Analytics screen')).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    /**
     * Çökme ölçüme gider — sahibin kilit kuralı gereği. Ön yüz çökmesi
     * bugüne kadar hiçbir yerde iz bırakmıyordu: hata tarayıcıda olduğu için
     * sunucu kaydına hiçbir şey düşmez.
     */
    it('çökmeyi ölçüme bildirir', () => {
        render(
            <AppErrorBoundary scope="route">
                <Bomb explode={true} />
            </AppErrorBoundary>,
        );

        expect(trackEvent).toHaveBeenCalledWith('frontend_error_boundary', {
            error_class: 'TypeError',
            boundary_scope: 'route',
        });
    });

    /**
     * Hata METNİ ölçüme GİTMEZ.
     *
     * Mesajlar sıklıkla veri taşır ("Cannot read 'email' of undefined").
     * dataLayer'a giren şey GTM üzerinden üçüncü taraflara akar ve geri
     * alınamaz; bu yüzden yalnız hata SINIFI gönderilir.
     */
    it('hata metnini ölçüme sızdırmaz', () => {
        render(
            <AppErrorBoundary scope="route">
                <Bomb explode={true} />
            </AppErrorBoundary>,
        );

        const payload = JSON.stringify(trackEvent.mock.calls);

        expect(payload).not.toContain('i.map is not a function');
    });
});
