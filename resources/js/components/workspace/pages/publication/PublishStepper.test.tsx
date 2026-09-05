import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { PublishStepper } from './PublishStepper';

/**
 * ADIM ÇİZGİSİ — kanonik kaynak `docs/reference/panel-v3/panel.dc.html`,
 * `data-screen-label="Yayınlama"`: Taslak (N değişiklik) → Önizleme
 * (telefonda kontrol) → Yayında (v14 · 2 gün önce).
 *
 * NEDEN: ekran bugün doğru bilgileri taşıyor ama sahibin NEREDE OLDUĞUNU
 * söylemiyor. Restoran sahibi paneli günde beş kez açar ve her açışında tek
 * bir soru sorar: "menüm şu an güncel mi?". Bugün bu sorunun cevabı üç ayrı
 * bölgeye dağılmış durumda — hazırlık listesi, durum cümlesi, sürüm
 * listesi. Adım çizgisi o üç cevabı tek bir satıra indirir.
 *
 * Hangi adımda olunduğu YALNIZ RENKLE söylenmez: her adımın durumu metin
 * olarak da yazılır (WCAG 1.4.1) ve şu anki adım `aria-current="step"`
 * taşır.
 */
function steps(): HTMLElement[] {
    const region = screen.getByRole('region', { name: /where you are/i });

    return within(region).getAllByRole('listitem');
}

describe('PublishStepper — sahip hangi adımda olduğunu okur', () => {
    it('üç adımı sırayla çizer', () => {
        render(
            <PublishStepper
                pendingChangeCount={3}
                previewOpen={false}
                liveVersion={14}
                publishedAt={null}
            />,
        );

        const labels = steps().map((step) => step.textContent ?? '');

        expect(labels).toHaveLength(3);
        expect(labels[0]).toMatch(/Draft/i);
        expect(labels[1]).toMatch(/Preview/i);
        expect(labels[2]).toMatch(/Live/i);
    });

    it('bekleyen değişiklik SAYISINI taslak adımının altına yazar', () => {
        render(
            <PublishStepper
                pendingChangeCount={3}
                previewOpen={false}
                liveVersion={14}
                publishedAt={null}
            />,
        );

        expect(steps()[0].textContent ?? '').toMatch(/3 changes waiting/i);
    });

    it('bekleyen değişiklik varken ŞU ANKİ adım Taslak’tır', () => {
        render(
            <PublishStepper
                pendingChangeCount={3}
                previewOpen={false}
                liveVersion={14}
                publishedAt={null}
            />,
        );

        expect(steps()[0]).toHaveAttribute('aria-current', 'step');
        expect(steps()[1]).not.toHaveAttribute('aria-current');
        expect(steps()[2]).not.toHaveAttribute('aria-current');
    });

    it('önizleme açıkken ŞU ANKİ adım Önizleme’dir', () => {
        render(
            <PublishStepper
                pendingChangeCount={3}
                previewOpen
                liveVersion={14}
                publishedAt={null}
            />,
        );

        expect(steps()[1]).toHaveAttribute('aria-current', 'step');
    });

    it('bekleyen değişiklik yokken ve yayın varken ŞU ANKİ adım Yayında’dır', () => {
        render(
            <PublishStepper
                pendingChangeCount={0}
                previewOpen={false}
                liveVersion={14}
                publishedAt={null}
            />,
        );

        expect(steps()[2]).toHaveAttribute('aria-current', 'step');
    });

    it('yayın adımı sürüm numarasını ve ne kadar önce yayınlandığını yazar', () => {
        /*
            Kaynağın cümlesi "v14 · 2 gün önce". Ham zaman damgası
            ("2026-09-03T09:00:00Z") sahibe hiçbir şey anlatmaz; "2 gün önce"
            ise "bu menü bayat mı?" sorusunun doğrudan cevabıdır.
        */
        const twoDaysAgo = new Date('2026-09-03T09:00:00Z');
        const now = new Date('2026-09-05T09:00:00Z');

        render(
            <PublishStepper
                pendingChangeCount={0}
                previewOpen={false}
                liveVersion={14}
                publishedAt={twoDaysAgo.toISOString()}
                now={now}
            />,
        );

        const live = steps()[2].textContent ?? '';

        expect(live).toMatch(/v14/);
        expect(live).toMatch(/2 days ago/i);
    });

    it('hiç yayın yokken yayın adımı UYDURMAZ, dürüstçe boş olduğunu söyler', () => {
        render(
            <PublishStepper
                pendingChangeCount={2}
                previewOpen={false}
                liveVersion={null}
                publishedAt={null}
            />,
        );

        const live = steps()[2].textContent ?? '';

        expect(live).toMatch(/Nothing published yet/i);
        expect(live).not.toMatch(/v\d/);
    });

    it('her adımın durumu METİN olarak da okunur — renk tek kanal değildir', () => {
        render(
            <PublishStepper
                pendingChangeCount={0}
                previewOpen={false}
                liveVersion={14}
                publishedAt={null}
            />,
        );

        const region = screen.getByRole('region', { name: /where you are/i });
        const text = region.textContent ?? '';

        expect(text).toMatch(/You are here/i);
        expect(text).toMatch(/Done/i);
    });

    it('sabit piksel, kırılım noktası, yasak ağırlık veya yön sınıfı taşımaz', () => {
        const { container } = render(
            <PublishStepper
                pendingChangeCount={3}
                previewOpen={false}
                liveVersion={14}
                publishedAt={null}
            />,
        );

        const classNames = Array.from(container.querySelectorAll<HTMLElement>('*'))
            .map((element) => (typeof element.className === 'string' ? element.className : ''))
            .join(' ');

        expect(classNames).not.toMatch(/(^|\s)(sm|md|lg|xl|2xl):/);
        expect(classNames).not.toMatch(/\[\d+px\]/);
        expect(classNames).not.toMatch(/font-semibold/);
        expect(classNames).not.toMatch(/rounded-full/);
        expect(classNames).not.toMatch(/uppercase/);
        expect(classNames).not.toMatch(/\b(ml|mr|pl|pr|text-left|text-right)-?/);
    });
});
