import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PageState } from './PageState';

/**
 * SAYFA DURUMLARI — `docs/59`.
 *
 * Bu testlerin ağırlığı görünüşte değil, ANLAMDA: hangi durumun uyarı olduğu,
 * hangisinin olmadığı, ve her durumun bir çıkış yolu taşıdığı.
 */
describe('PageState (docs/59)', () => {
    /**
     * Yalnız `error` gerçek bir arızadır.
     *
     * Boş bir liste, eksik bir ön koşul, satın alınmamış bir plan ya da
     * olmayan bir yetki — hiçbirinde bozulmuş bir şey yoktur. `role="alert"`
     * ekran okuyucuyu böler ve aciliyet bildirir; normal bir durum için
     * kullanmak, gerçek uyarının değerini düşürür.
     */
    it('yalnız hata durumu uyarı olarak duyurulur', () => {
        const { unmount } = render(
            <PageState kind="error" title="Could not load" action={<button>Retry</button>} />,
        );

        expect(screen.getByRole('alert')).toBeInTheDocument();
        unmount();

        for (const kind of ['empty', 'permission', 'planRestricted', 'prerequisite'] as const) {
            const view = render(
                <PageState kind={kind} title="Nothing yet" whyNoAction="Ask an owner." />,
            );

            expect(screen.queryByRole('alert')).toBeNull();
            expect(screen.getByRole('status')).toBeInTheDocument();
            view.unmount();
        }
    });

    /**
     * Bu depoda bir kez yaşandı: Analytics'in 402 yanıtı hata gibi sunuluyor ve
     * "tekrar deneyin" diyordu. Plan satın alınmadıkça tekrar denemek hiçbir
     * zaman işe yaramaz; kullanıcı aynı düğmeye basıp durur.
     */
    it('plan kısıtı hata değildir ve tekrar deneme sunmaz', () => {
        render(
            <PageState
                kind="planRestricted"
                title="Reporting is not included in your current plan"
                description="No data is lost — it keeps being collected."
                action={<button>View your plan</button>}
            />,
        );

        expect(screen.queryByRole('alert')).toBeNull();
        expect(screen.queryByRole('button', { name: /retry|try again/i })).toBeNull();
        expect(screen.getByRole('button', { name: 'View your plan' })).toBeInTheDocument();
    });

    /**
     * Çıkışsız bir boş durum, kullanıcıyı "burada yapılacak bir şey yok" diye
     * bırakır. Eylem yoksa NEDEN olmadığı söylenmeli — yetki kendine
     * verilemez, ama kimden isteneceği söylenebilir.
     */
    it('eylem yoksa nedenini söyler', () => {
        render(
            <PageState
                kind="permission"
                title="You cannot change these details"
                whyNoAction="Ask an owner or manager of this workspace."
            />,
        );

        expect(screen.getByText(/ask an owner or manager/i)).toBeInTheDocument();
    });

    /**
     * `Spinner` kendi canlı bölgesini taşır; kap ikincisini KURMAZ.
     *
     * İç içe iki `role="status"`, aynı şeyi iki kez duyurur — ve tekrar eden
     * duyurular gerçek olanları da bastırır.
     */
    it('yükleniyor durumunda tek bir canlı bölge bulunur', () => {
        render(<PageState kind="loading" title="Checking your menu…" />);

        const statuses = screen.getAllByRole('status');

        expect(statuses).toHaveLength(1);
        expect(statuses[0]).toHaveTextContent(/checking your menu/i);
    });

    /**
     * Ön koşul durumu, kullanıcıyı EKSİK ADIMA götürür — "menü yok" demekle
     * yetinmez. Boş durum dört soruyu birden cevaplamalı: ne yok, neden yok,
     * anlamı ne, şimdi ne yapabilir.
     */
    it('ön koşul durumu eksik adıma götürür', () => {
        render(
            <PageState
                kind="prerequisite"
                title="QR codes point at a published menu"
                description="Build your menu first, then come back here to print the codes."
                action={<button>Go to your menu</button>}
            />,
        );

        expect(screen.getByRole('status')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Go to your menu' })).toBeInTheDocument();
    });
});
