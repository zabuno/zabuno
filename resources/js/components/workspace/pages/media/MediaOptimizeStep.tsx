import { useId } from 'react';
import { ShieldCheck } from '@phosphor-icons/react';

import {
    MAX_MAX_EDGE,
    MIN_MAX_EDGE,
    measuredSaving,
    planDownscale,
    type DownscalePlan,
} from './clientDownscale';
import { type Size } from './cropGeometry';
import { formatBytes } from './mediaFormat';
import { wizardText } from './uploadWizardCopy';

export type MediaOptimizeStepProps = {
    source: Size;
    minimum: Size;
    aspect: string | null;
    maxEdge: number;
    onMaxEdge: (pixels: number) => void;
    /** Kullanıcının seçtiği dosyanın GERÇEK boyutu. */
    beforeBytes: number;
    /** Küçültülmüş çıktının GERÇEK boyutu; henüz üretilmediyse `null`. */
    afterBytes: number | null;
    busy: boolean;
};

/**
 * 2. adım — İSTEMCİ OPTİMİZASYONU (kanonik kaynak: "Yükle" ekranı,
 * "Telefonda küçültüldü").
 *
 * Restoran sahibinin yolculuğu: mutfakta telefonla lahmacun fotoğrafı
 * çekiyor, dosya 8 MB. Bugün o 8 MB olduğu gibi ağa çıkıyor, mobil veriden
 * düşüyor ve kotadan 8 MB yiyor — sunucu görseli zaten küçülteceği hâlde.
 * Bu adım küçültmeyi yüklemeden ÖNCE, sahibin kendi telefonunda yapıyor.
 *
 * Bu aynı zamanda bir GÜVENLİK kararıdır (`docs/108` §4): dosya kullanıcının
 * kendi makinesinde küçülür, taranmamış hâliyle sunucuya gidip oradan geri
 * servis edilmez.
 *
 * Ekrandaki yüzde ÖLÇÜLMÜŞTÜR. İki gerçek dosya boyutu arasındaki farktır;
 * tahmin edilmiş bir kazanç bir kez yanlış çıktığında kullanıcı bir daha
 * hiçbir sayıya inanmaz.
 */
export function MediaOptimizeStep({
    source,
    minimum,
    aspect,
    maxEdge,
    onMaxEdge,
    beforeBytes,
    afterBytes,
    busy,
}: MediaOptimizeStepProps) {
    const edgeId = useId();
    const plan: DownscalePlan = planDownscale({ source, minimum, aspect, maxEdge });
    const saving = afterBytes === null ? null : measuredSaving(beforeBytes, afterBytes);

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            {saving ? (
                <div className="flex flex-col gap-[var(--space-1)] rounded-[var(--radius-lg)] border border-border-success bg-surface-success p-[var(--space-3)]">
                    <p className="text-body font-bold text-fg">
                        {wizardText('workspace.media.upload.optimize.heading')}
                    </p>
                    {/*
                        Yüzde bir ÖLÇÜDÜR ve kaydırıcı sürüklenirken her karede
                        değişir: orantılı rakamda satır yatayda oynar ve
                        okunması gereken sayı okunamaz olur.
                    */}
                    <p className="text-body font-bold text-fg tabular-nums">
                        {wizardText('workspace.media.upload.optimize.saved', {
                            percent: String(saving.percent),
                        })}
                    </p>
                    <p className="text-body text-fg-secondary tabular-nums">
                        {wizardText('workspace.media.upload.optimize.savedNote', {
                            before: formatBytes(beforeBytes),
                            after: formatBytes(afterBytes ?? 0),
                        })}
                    </p>
                </div>
            ) : null}

            {!plan.apply && !busy ? (
                <p className="text-body text-fg-muted">
                    {wizardText('workspace.media.upload.optimize.none')}
                </p>
            ) : null}

            {busy ? (
                <p role="status" className="text-body text-fg-muted">
                    {wizardText('workspace.media.upload.optimize.working')}
                </p>
            ) : null}

            <div className="flex flex-col gap-[var(--space-1)]">
                <label htmlFor={edgeId} className="text-body text-fg-secondary">
                    {wizardText('workspace.media.upload.optimize.longEdge')}
                </label>
                <input
                    id={edgeId}
                    type="range"
                    min={MIN_MAX_EDGE}
                    max={MAX_MAX_EDGE}
                    step={256}
                    value={maxEdge}
                    onChange={(event) => onMaxEdge(Number(event.target.value))}
                    className="min-h-[var(--control-height)] w-full"
                />
                {/* Ölçü: değişken rakam, hizalı kolon. */}
                <p className="text-meta text-fg-muted tabular-nums">
                    {wizardText('workspace.media.upload.optimize.longEdge.value', {
                        pixels: String(maxEdge),
                    })}
                </p>
            </div>

            {plan.apply ? (
                <p aria-live="polite" className="text-body text-fg-secondary tabular-nums">
                    {wizardText('workspace.media.upload.optimize.result', {
                        width: String(plan.target.width),
                        height: String(plan.target.height),
                    })}
                </p>
            ) : null}

            {/*
                Taban kazandığında NEDEN durduğu söylenir.

                Sessizce 1200'de durmak kaydırıcıyı bozuk gösterirdi;
                kullanıcının istediği 1000'e inmek ise dosyayı slot için
                kullanılamaz yapardı ve bunu ancak sunucu reddettikten sonra
                öğrenirdi. Kırpma piksel eklemez — bu cümle o kuralın
                kullanıcıya bakan yüzüdür.
            */}
            {plan.limitedBy === 'slotMinimum' ? (
                <p className="text-body text-fg-muted tabular-nums">
                    {wizardText('workspace.media.upload.optimize.floor', {
                        width: String(minimum.width),
                        height: String(minimum.height),
                    })}
                </p>
            ) : null}

            <p className="flex items-start gap-[var(--space-2)] text-body text-fg-muted">
                <ShieldCheck size={20} aria-hidden="true" className="shrink-0" />
                {wizardText('workspace.media.upload.optimize.privacy')}
            </p>
        </div>
    );
}

export default MediaOptimizeStep;
