import { type ClassValue, clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

/**
 * Tasarım sisteminin rol adlı ölçekleri `tailwind-merge`'e TANITILIR.
 *
 * Bu, sessiz bir hataydı ve bir görsel test yakaladı. `tailwind-merge`
 * tanımadığı bir `text-*` sınıfını RENK sanar. Ölçeğimiz rol adlı olduğu
 * için (`text-body`, `text-meta`) birleştirici onları renk grubuna koyuyor
 * ve aynı gruptaki önceki sınıfı siliyordu:
 *
 *     twMerge('text-fg', 'text-body')  →  'text-body'      ← renk KAYBOLDU
 *     twMerge('text-fg', 'text-sm')    →  'text-fg text-sm' ← doğru
 *
 * Yani ölçeği yayınladığımız günden beri, `cn()` kullanılan her yerde metin
 * rengi sessizce düşüyordu. Görünür bir hata vermiyordu çünkü tarayıcı
 * rengi miras alıyor — yanlış rengi.
 *
 * Aşağıdaki tanıtım, bu sınıfların yazı BOYUTU olduğunu söyler; böylece
 * renkle aynı grupta yarışmazlar.
 *
 * `--text-*` ve `--container-*` token'ları `resources/css/app.css`
 * içindedir; buradaki liste onlarla aynı kalmalı.
 */
const twMerge = extendTailwindMerge({
    extend: {
        classGroups: {
            'font-size': [{ text: ['title', 'section', 'subsection', 'body', 'meta'] }],
            'max-w': [{ 'max-w': ['form', 'content', 'table'] }],
            rounded: [{ rounded: ['pill'] }],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
