import { useEffect, useState } from 'react';
import {
    detectDivergence,
    isBannerEnabled,
    shortRevision,
    type BuildDivergence,
} from '@/lib/build';
import { trackEvent } from '@/lib/analytics';

/**
 * Ekranda çalışan sürüm, sunucunun sunduğu sürümden farklıysa söyler.
 *
 * Tasarımın tek önemli kararı ŞU: bu bileşen bir şey ters gitmedikçe HİÇBİR
 * şey çizmez. Kalıcı bir sürüm rozeti değildir ve bilerek değildir.
 *
 * Kalıcı bir rozet bu sorunu çözmez, çünkü sorun "sürüm bilgisinin
 * bulunamaması" değildi — sahibin BAKMASI GEREKTİĞİNİ BİLMEMESİydi. Arayüz
 * normal görünüyordu. Gidip bakılması gereken bir numara, bakılması
 * gerektiği bilinmediği sürece hiçbir şeyi engellemez. Bu yüzden mekanizma
 * çekme değil İTMEDİR: ayrışmayı uygulama fark eder ve kendisi söyler.
 *
 * Ayrıca kabukta kalıcı bir öğe daha eklemek, "ölü kontrol bulunmayacak"
 * kuralına da aykırı olurdu.
 */
export function BuildTruthBanner(): React.JSX.Element | null {
    /**
     * Ayrışma İLK render'da hesaplanır, efektle sonradan değil.
     *
     * Okunan şey sunucunun bastığı meta etiketleridir: `<head>` içinde,
     * React çalışmadan önce oradadırlar ve hiç değişmezler. Efektte
     * hesaplayıp `setState` çağırmak, her yüklemede gereksiz bir ikinci
     * render turu üretirdi — ve şerit çoğu zaman HİÇBİR ŞEY çizmeyeceği için
     * bu, tamamen boşa yapılan bir turdur.
     */
    const [divergence] = useState<BuildDivergence>(() =>
        isBannerEnabled() ? detectDivergence() : { kind: 'fresh' },
    );

    useEffect(() => {
        if (divergence.kind === 'fresh') {
            return;
        }

        // Ayrışma ölçüme de gider. Sahibin kilit kuralı her şeyin tenant
        // bazında gözlenebilmesi; bir ortamın yanlış sürüm sunması da buna
        // dahildir ve şu ana kadar hiçbir yerde iz bırakmıyordu.
        trackEvent('build_divergence_detected', { divergence_kind: divergence.kind });
    }, [divergence]);

    if (divergence.kind === 'fresh') {
        return null;
    }

    return (
        <div
            role="alert"
            className="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-border-warning bg-surface-warning px-4 py-2 text-body text-fg-warning"
        >
            <strong className="font-semibold">
                {divergence.kind === 'stale-build'
                    ? 'This page is running an older build.'
                    : 'This page and its assets come from different revisions.'}
            </strong>
            <span className="basis-full text-fg-muted">
                {divergence.kind === 'stale-build' ? (
                    <>
                        Source files changed after the last build. Run{' '}
                        <code className="font-mono">npm run build</code> to see your changes.
                    </>
                ) : (
                    <>
                        Server <code className="font-mono">{shortRevision(divergence.served)}</code>
                        , assets{' '}
                        <code className="font-mono">{shortRevision(divergence.running)}</code>. What
                        you see is not what this checkout contains.
                    </>
                )}
            </span>
        </div>
    );
}
