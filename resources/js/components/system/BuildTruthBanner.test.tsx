import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { BuildTruthBanner } from './BuildTruthBanner';

const trackEvent = vi.hoisted(() => vi.fn());
vi.mock('@/lib/analytics', () => ({ trackEvent }));

function setMeta(name: string, content: string): void {
    const element = document.createElement('meta');
    element.setAttribute('name', name);
    element.setAttribute('content', content);
    document.head.append(element);
}

/** Sunucunun bastığı normal sayfa: şerit açık, sıcak sunucu yok. */
function serverSays(revision: string, stale: 'true' | 'false'): void {
    setMeta('zabuno-build-banner', 'true');
    setMeta('zabuno-build-hot', 'false');
    setMeta('zabuno-build-revision', revision);
    setMeta('zabuno-build-stale', stale);
}

afterEach(() => {
    document.head.querySelectorAll('meta[name^="zabuno-build"]').forEach((node) => node.remove());
    vi.unstubAllGlobals();
    trackEvent.mockClear();
});

describe('BuildTruthBanner (docs/52)', () => {
    /**
     * Tasarımın en önemli davranışı: her şey yolundayken HİÇBİR ŞEY çizmez.
     *
     * Kalıcı bir sürüm rozeti sorunu çözmezdi — sorun sürüm bilgisinin
     * bulunamaması değil, sahibin BAKMASI GEREKTİĞİNİ BİLMEMESİydi. Ayrıca
     * kabukta kalıcı bir öğe daha, "ölü kontrol bulunmayacak" kuralını
     * çiğnerdi.
     */
    it('sürümler eşitken hiçbir şey çizmez', () => {
        serverSays('a'.repeat(40), 'false');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'a'.repeat(40));

        const { container } = render(<BuildTruthBanner />);

        expect(container).toBeEmptyDOMElement();
        expect(trackEvent).not.toHaveBeenCalled();
    });

    it('sürümler ayrıştığında iki sürümü de göstererek uyarır', () => {
        serverSays('a'.repeat(40), 'false');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'b'.repeat(40));

        render(<BuildTruthBanner />);

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(screen.getByText('aaaaaaa')).toBeInTheDocument();
        expect(screen.getByText('bbbbbbb')).toBeInTheDocument();
    });

    /**
     * Uyarı, NE YAPILACAĞINI söylemeli. "Bir şeyler ters" diyen ama çıkış yolu
     * vermeyen bir uyarı, kullanıcıyı sıkışmış bırakır.
     */
    it('bayat derlemede çalıştırılacak komutu söyler', () => {
        serverSays('a'.repeat(40), 'true');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'a'.repeat(40));

        render(<BuildTruthBanner />);

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(screen.getByText('npm run build')).toBeInTheDocument();
    });

    it('ayrışmayı tenant ölçümüne bildirir', () => {
        serverSays('a'.repeat(40), 'true');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'a'.repeat(40));

        render(<BuildTruthBanner />);

        expect(trackEvent).toHaveBeenCalledWith('build_divergence_detected', {
            divergence_kind: 'stale-build',
        });
    });

    /**
     * Sunucu şeridi kapattıysa (üretim varsayılanı) hiçbir şey çizilmez.
     * Sessizleştirilen yalnız EKRAN'dır; ölçüm olayı da bu yolda üretilmez,
     * çünkü tespit hiç çalıştırılmaz.
     */
    it('sunucu şeridi kapattıysa çizmez', () => {
        setMeta('zabuno-build-banner', 'false');
        setMeta('zabuno-build-hot', 'false');
        setMeta('zabuno-build-revision', 'a'.repeat(40));
        setMeta('zabuno-build-stale', 'true');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'b'.repeat(40));

        const { container } = render(<BuildTruthBanner />);

        expect(container).toBeEmptyDOMElement();
    });

    /**
     * Sıcak geliştirme sunucusu altında bayatlık kavramı yoktur; burada uyarmak
     * geliştiricinin en çok çalıştığı anda sürekli yanlış alarm demek olurdu —
     * ve sürekli yanlış alarm veren bir uyarı, kapatılan bir uyarıdır.
     */
    it('sıcak geliştirme sunucusu altında susar', () => {
        setMeta('zabuno-build-banner', 'true');
        setMeta('zabuno-build-hot', 'true');
        setMeta('zabuno-build-revision', 'a'.repeat(40));
        setMeta('zabuno-build-stale', 'true');
        vi.stubGlobal('__ZABUNO_BUILD_REVISION__', 'b'.repeat(40));

        const { container } = render(<BuildTruthBanner />);

        expect(container).toBeEmptyDOMElement();
    });
});
