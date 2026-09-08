import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { QrPrintLookStep } from './QrPrintLookStep';
import type { QrPrintPlan } from './qrPrintPlan';

/**
 * KODUN KENDİSİ HER TASARIMDA KOYU BASILIR — ve bunu sahip OKUMALI.
 *
 * Kısıt yeni değil, kaybolan şey söylenmesiydi. Cümle panel v3'te
 * `QrSelectedCodePanel` üzerinde duruyordu; o bileşen panel v3.1 QR ekranı
 * yeniden yazıldığında çağıransız kaldı ve FF-170'te silindi. Cümlenin
 * anlattığı kısıt üç yerde kayıtlı kaldı (`CardTheme`, `docs/109` §238 karar
 * tablosu, `QrCardSvgTest`) ama üçü de geliştiricinin okuduğu yerler. Sahip
 * hiçbirini açmıyor.
 *
 * Aradaki fark şu yüzden önemli: aynı ekran ARTIK "Koyu" ve "Tabela"
 * tasarımlarını sunuyor. Sahip koyu kartı seçiyor, üçüncü adımdaki maketin
 * içinde kodun hâlâ açık zeminde durduğunu görüyor ve bunun neden böyle
 * olduğunu hiçbir yerde okumuyor. O boşlukta iki yanlış sonuçtan biri doğuyor:
 * ya maketi bozuk sanıyor, ya da "koyu kart koyu kod demek" diye düşünüp
 * kırk kart bastırdıktan sonra telefonların kodu okumadığını öğreniyor.
 *
 * Bu test cümlenin ÇİZİLDİĞİNİ donduruyor, metnini değil: kelimeler
 * çevrilebilir, kısıtın söylenmesi tartışmaya açık değil.
 *
 * Requirement IDs: QR-CODE-ALWAYS-DARK-VISIBLE-01.
 */
describe('karekod tasarım adımı — kodun rengi kısıtı', () => {
    const plan: QrPrintPlan = {
        preset: 'table',
        custom: false,
        size: 'A5',
        landscape: false,
        format: 'pdf',
        scope: 'one',
        areaId: null,
        codeId: 4021,
        theme: 'minimal',
        headline: '',
    };

    function renderStep(theme: QrPrintPlan['theme']) {
        return render(
            <QrPrintLookStep
                plan={{ ...plan, theme }}
                onChange={() => {}}
                brandPrimaryColor="#8B1D3F"
            />,
        );
    }

    // --- QR-CODE-ALWAYS-DARK-VISIBLE-01 ----------------------------------

    it('koyu tasarım seçiliyken kodun neden koyu basıldığını söyler', () => {
        renderStep('dark');

        expect(
            screen.getByText(/dark-on-light/i),
            'Sahip "Koyu"yu seçtiği anda kısıtı okumalı; kodun rengi kartın rengiyle değişmiyor.',
        ).toBeInTheDocument();
    });

    /**
     * KOŞULSUZ ÇİZİLİR ve bu bilinçli bir karar.
     *
     * Cümle yalnız `dark`/`signage` seçiliyken görünseydi, o tasarımlara
     * iliştirilmiş bir UYARI gibi okunurdu — "koyuyu seçtin, dikkat" — oysa
     * anlattığı şey beş tasarımın tamamı için geçerli bir kural. Cümlenin
     * kendi sözü de bunu söylüyor: "kart nasıl görünürse görünsün". Koşullu
     * çizim, cümleyi kendi ifadesiyle çelişkiye düşürürdü.
     */
    it('sade tasarımda da aynı cümleyi çizer, kurala uyarıya dönüştürmez', () => {
        renderStep('classic');

        expect(
            screen.getByText(/dark-on-light/i),
            'Kural beş tasarımın tamamı için geçerli; yalnız koyuda göstermek onu uyarıya çevirirdi.',
        ).toBeInTheDocument();
    });

    /**
     * SEBEP DE YAZILIR, yalnız kural değil.
     *
     * "Kod her zaman koyu basılır" tek başına keyfi bir kısıt gibi durur ve
     * keyfi görünen kısıtlar aşılmaya çalışılır. Sahibin durmasını sağlayan
     * şey sebep: ters basılan kodu birçok telefon hiç okumuyor.
     */
    it('kuralın sebebini de söyler — telefonların ters kodu okumadığını', () => {
        renderStep('signage');

        expect(screen.getByText(/not read at all by many phones/i)).toBeInTheDocument();
    });
});
