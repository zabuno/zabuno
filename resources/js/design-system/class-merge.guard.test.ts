import { describe, expect, it } from 'vitest';
import { twMerge } from 'tailwind-merge';
import { cn } from '../lib/utils';

/**
 * DS-MERGE-SAFETY — rol adlı ölçek metin rengini silmemeli.
 *
 * `tailwind-merge`, tanımadığı bir `text-*` sınıfını RENK sanar ve aynı
 * gruptaki önceki sınıfı atar. Rol adlı tipografi ölçeğimiz tam olarak bu
 * tuzağa düştü:
 *
 *     twMerge('text-fg', 'text-body')  →  'text-body'
 *
 * Yani ölçek yayınlandığı günden beri, sınıf birleştirmenin kullanıldığı
 * her yerde metin rengi sessizce düşüyordu. Görünür bir hata vermiyordu —
 * tarayıcı rengi miras alıyor, sadece yanlış olanı. Bir görsel test
 * yakaladı; gözle fark edilmesi zordu.
 *
 * İki yol var ve ikisi de burada korunuyor:
 *
 * 1. Kendi `cn()`'imiz birleştiriciye ölçeği TANITIR.
 * 2. Flowbite KENDİ twMerge örneğini kullanır ve ona ulaşamayız; orada
 *    `text-[length:…]` yazımı kullanılır — stok birleştiricinin kesinlikle
 *    yazı boyutu olarak tanıdığı biçim.
 */

const SCALE = ['title', 'section', 'subsection', 'body', 'meta'] as const;

describe('DS-MERGE-SAFETY — boyut ve renk birlikte yaşar', () => {
    it('kendi birleştiricimiz ölçeği yazı boyutu olarak tanır', () => {
        for (const step of SCALE) {
            const merged = cn('text-fg', `text-${step}`);

            expect(merged, `DS-MERGE-SAFETY-01: \`text-${step}\` metin rengini siliyor.`).toContain(
                'text-fg',
            );
            expect(merged).toContain(`text-${step}`);
        }
    });

    it('iki boyut yine çakışır — sonuncusu kazanır', () => {
        expect(cn('text-body', 'text-meta')).toBe('text-meta');
    });

    it('genişlik ve yarıçap ölçekleri de tanıtılmış', () => {
        expect(cn('max-w-content', 'max-w-form')).toBe('max-w-form');
        expect(cn('rounded-lg', 'rounded-pill')).toBe('rounded-pill');
    });

    it('Flowbite temasının yazımı STOK birleştiricide de güvenli', () => {
        // Flowbite'ın kendi örneğine ulaşamıyoruz; bu yüzden orada
        // kullanılan yazımın tanıtım OLMADAN da doğru olması gerekir.
        for (const step of ['body', 'meta']) {
            const merged = twMerge('text-fg', `text-[length:var(--text-${step})]`);

            expect(
                merged,
                `DS-MERGE-SAFETY-02: Flowbite yazımı \`text-${step}\` için rengi siliyor.`,
            ).toContain('text-fg');
        }
    });
});
