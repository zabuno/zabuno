import { t } from '../../../../i18n/workspace';

/**
 * Canlı önizleme şeridi (FF-128).
 *
 * Tema ve yoğunluk seçicileri kendi görünümlerini değiştirmez: kullanıcı
 * "sıkışık"a bastığında ekranın geri kalanı değişir ama düğmenin kendisi
 * aynı kalır, yani SEÇİMİN SONUCU görünmez. Kullanıcı ne yaptığını anlamak
 * için sayfayı terk edip bir listeye gitmek zorunda kalıyordu.
 *
 * Şerit, seçimin uygulandığı gerçek jetonları kullanan küçük bir örnektir:
 * kart yüzeyi, kenarlık, satır dolgusu ve bir kontrol. Sahte bir resim değil
 * — aynı `--density-*` ve `--color-*` değerlerini okur, dolayısıyla yalan
 * söyleyemez.
 */
export function AppearancePreview() {
    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            <h4 className="text-body font-bold text-fg">
                {t('workspace.profile.preview.heading')}
            </h4>
            <p className="text-body text-fg-secondary">{t('workspace.profile.preview.help')}</p>

            {/*
                `aria-hidden` DEĞİL ama bir örnek olduğu söylenir: ekran
                okuyucu kullanan biri buradaki "Aç" düğmesini gerçek sanıp
                aramamalı, ama bölümün var olduğunu da bilmeli.
            */}
            <div
                role="group"
                aria-label={t('workspace.profile.preview.heading')}
                className="rounded-[var(--radius-lg)] border border-border bg-surface"
            >
                {/*
                    SATIRLARDA KONTROL YOK — ve bu bilinçli.

                    İlk hâlinde her satırda bir düğme vardı ve şerit
                    yoğunluğa HİÇ tepki vermiyordu: satırın yüksekliğini
                    dolgu değil, içindeki 44 piksellik dokunma hedefi
                    belirliyordu. Yani önizleme, seçimin sonucunu göstermek
                    yerine göstermediğini gizliyordu.

                    Yoğunluğun gerçekten göründüğü yer metin satırlarıdır;
                    kontrol ayrı durur ve tam da DEĞİŞMEDİĞİNİ gösterir.
                */}
                {[0, 1, 2].map((row) => (
                    <div
                        key={row}
                        className="flex min-h-[var(--density-row-height)] items-center justify-between gap-[var(--space-3)] border-b border-border px-[var(--density-padding-inline)] py-[var(--space-1)] last:border-b-0"
                    >
                        <span className="text-meta text-fg-muted">
                            {t('workspace.profile.preview.sampleLabel')}
                        </span>
                        <span className="text-body font-medium text-fg">
                            {t('workspace.profile.preview.sampleValue')} {row + 1}
                        </span>
                    </div>
                ))}
            </div>

            {/*
                Dokunma hedefi HİÇBİR modda küçülmez (`--density-hit-area-min`,
                üç modda da 44px). Yardım metni bunu söylüyor; şerit de
                göstermeli, yoksa söz kanıtsız kalır.
            */}
            <span
                aria-hidden="true"
                className="inline-flex min-h-[var(--control-height)] w-fit items-center rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-2)] text-body whitespace-nowrap text-fg-secondary"
            >
                {t('workspace.profile.preview.sampleAction')}
            </span>
        </div>
    );
}

export default AppearancePreview;
