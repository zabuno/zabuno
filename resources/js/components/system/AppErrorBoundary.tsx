import { Component, type ErrorInfo, type ReactNode } from 'react';
import { servedRevision, shortRevision } from '@/lib/build';
import { trackEvent } from '@/lib/analytics';

type Props = {
    children: ReactNode;
    /**
     * Değiştiğinde sınır kendini sıfırlar — pratikte geçerli rota.
     *
     * Kurtarmayı GERÇEK yapan şey budur. React bir hata yakaladıktan sonra
     * o ağacı kalıcı olarak bozuk sayar: anahtar olmadan, kullanıcı başka
     * bir ekrana gitse bile hata ekranı kalırdı ve tek çıkış sayfayı
     * yenilemek olurdu. O da bozuk ekrana geri dönmek demektir.
     */
    resetKey?: string;
    /** Kabuğun kendisi mi çöktü, yoksa yalnız bir sayfa mı? */
    scope?: 'app' | 'route';
};

type State = { error: Error | null };

/**
 * Bir render hatasını boş ekran olmaktan çıkarıp kurtarılabilir bir yüzeye çevirir.
 *
 * Bu paket yazılmadan önce depoda TEK BİR hata sınırı yoktu. Bunun sonucu
 * teorik değil, gözlenmişti: `i.map is not a function` hatası bütün paneli
 * bomboş bir sayfaya çeviriyordu. Boş sayfa, kullanıcı için en kötü arıza
 * biçimidir — ne olduğunu, kimin suçu olduğunu, ne yapacağını söylemez;
 * çoğu kullanıcı bunu "internetim gitti" diye yorumlar ve bildirmez bile.
 *
 * Ekranda sürüm kimliği de gösterilir. Hata anı, sahibin hangi derlemeye
 * baktığını bilmesi gereken TEK andır ve tam o anda uygulamanın geri kalanı
 * artık çalışmamaktadır.
 */
export class AppErrorBoundary extends Component<Props, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        // Çökme ölçüme gider. Sahibin kilit kuralı her şeyin tenant bazında
        // gözlenebilmesi; ön yüz çökmesi şu ana kadar bunun tamamen dışındaydı
        // — sunucu kaydına hiçbir şey düşmez, çünkü hata tarayıcıda olur.
        //
        // Yalnız hata SINIFI ve sınır kapsamı gönderilir. Hata METNİ
        // gönderilmez: mesajlar sıklıkla veri taşır ("Cannot read 'email' of
        // undefined") ve dataLayer'a giren veri üçüncü taraflara akar,
        // geri alınamaz.
        //
        // Alan adı `error_class`, `error_name` DEĞİL: kişisel veri süzgeci
        // `name` alt dizesini arar ve `error_name` ona takılıyordu — yani bu
        // olay geliştirmede hata fırlatıyor, ÜRETİMDE İSE SESSİZCE
        // DÜŞÜYORDU. Boru hattının tek gerçek olayı hiç akmamıştı (FF-167).
        trackEvent('frontend_error_boundary', {
            error_class: error.name,
            boundary_scope: this.props.scope ?? 'route',
        });

        if (import.meta.env?.MODE !== 'production') {
            console.error('[Zabuno] Render error caught by boundary:', error, info.componentStack);
        }
    }

    componentDidUpdate(previous: Props): void {
        if (this.state.error !== null && previous.resetKey !== this.props.resetKey) {
            this.setState({ error: null });
        }
    }

    render(): ReactNode {
        const { error } = this.state;

        if (error === null) {
            return this.props.children;
        }

        const revision = servedRevision();
        const isDevelopment = import.meta.env?.MODE !== 'production';

        return (
            <div role="alert" className="mx-auto flex w-full max-w-content flex-col gap-4 p-6">
                <div className="flex flex-col gap-2 rounded-lg border border-border-danger bg-surface-danger p-4">
                    <h1 className="text-section font-bold text-fg-danger">
                        {this.props.scope === 'app'
                            ? 'Zabuno could not start this page.'
                            : 'This screen ran into an error.'}
                    </h1>
                    <p className="text-body text-fg-secondary">
                        {this.props.scope === 'app'
                            ? 'Nothing was saved or changed. Reloading usually clears it.'
                            : 'Your data is safe and nothing was changed. Other screens still work — use the navigation to move on, or reload this one.'}
                    </p>
                </div>

                <div className="flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                        className="rounded-md bg-fg px-4 py-2 text-body font-medium text-surface"
                    >
                        Reload this page
                    </button>
                </div>

                {/*
                    Sürüm, hata mesajından ÖNCE gelir ve üretimde de gösterilir:
                    bir kusuru bildiren kişinin verebileceği en değerli tek
                    bilgi, hangi derlemeye baktığıdır. Hata metni ise yalnız
                    geliştirmede görünür — üretim kullanıcısına anlam ifade
                    etmez ve iç yapı sızdırır.
                */}
                {revision !== null && (
                    <p className="text-body text-fg-muted">
                        Build <code className="font-mono">{shortRevision(revision)}</code>
                    </p>
                )}

                {isDevelopment && (
                    <pre className="overflow-x-auto rounded-md bg-surface-subtle p-3 text-body text-fg-secondary">
                        {error.name}: {error.message}
                    </pre>
                )}
            </div>
        );
    }
}
