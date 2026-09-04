import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { MediaOptimizeStep } from './MediaOptimizeStep';

/**
 * KAZANÇ ÖLÇÜLÜR — sihirbazın 2. adımı (kanonik kaynak: "Yükle" ekranı,
 * "Telefonda küçültüldü").
 *
 * Restoran sahibinin yolculuğu: mutfakta telefonla lahmacun fotoğrafı
 * çekiyor, dosya 8 MB. Bugün o 8 MB olduğu gibi ağa çıkıyor ve kotadan 8 MB
 * düşüyor — sunucu görseli zaten küçülteceği hâlde. Bu adım küçültmeyi
 * yüklemeden ÖNCE, sahibin kendi telefonunda yapıyor.
 *
 * Ekranda yazan yüzde bir PAZARLAMA CÜMLESİ DEĞİL, iki gerçek dosya boyutu
 * arasındaki farktır. Tahmine dayalı bir yüzde bir kez yanlış çıktığında
 * kullanıcı bir daha hiçbir sayıya inanmaz.
 */
const NOOP = () => {};

describe('MediaOptimizeStep — ölçülen kazanç', () => {
    it('gerçek bayt farkını yüzde ve boyut olarak yazar', () => {
        render(
            <MediaOptimizeStep
                source={{ width: 4000, height: 3000 }}
                minimum={{ width: 0, height: 0 }}
                aspect={null}
                maxEdge={2560}
                onMaxEdge={NOOP}
                beforeBytes={8_000_000}
                afterBytes={1_120_000}
                busy={false}
            />,
        );

        expect(screen.getByText('86% smaller')).toBeInTheDocument();
        // "Öncesi → sonrası" iki gerçek dosya boyutudur, yuvarlanmış bir tahmin değil.
        expect(screen.getByText(/1.1 MB will be sent instead of 7.6 MB/i)).toBeInTheDocument();
    });

    it('kazanç yoksa kazanç İDDİA ETMEZ', () => {
        /*
            Zaten küçük bir fotoğrafı yeniden kodlamak çoğu zaman büyütür.
            "%0 küçüldü" yazmak bile yanlış olurdu: yapılmamış bir işi
            yapılmış göstermek, sonraki her sayıyı şüpheli hâle getirir.
        */
        render(
            <MediaOptimizeStep
                source={{ width: 900, height: 600 }}
                minimum={{ width: 0, height: 0 }}
                aspect={null}
                maxEdge={2560}
                onMaxEdge={NOOP}
                beforeBytes={140_000}
                afterBytes={null}
                busy={false}
            />,
        );

        expect(screen.queryByText(/smaller$/)).toBeNull();
        expect(screen.getByText(/already small enough/i)).toBeInTheDocument();
    });

    it('sayılar tabular-nums ile hizalanır', () => {
        // Yüzde ve boyut kaydırıcı sürüklenirken HER karede değişir; orantılı
        // rakamda satır yatayda oynar ve okunacak sayı okunamaz olur.
        render(
            <MediaOptimizeStep
                source={{ width: 4000, height: 3000 }}
                minimum={{ width: 0, height: 0 }}
                aspect={null}
                maxEdge={2560}
                onMaxEdge={NOOP}
                beforeBytes={8_000_000}
                afterBytes={1_120_000}
                busy={false}
            />,
        );

        expect(screen.getByText('86% smaller')).toHaveClass('tabular-nums');
    });
});

describe('MediaOptimizeStep — slotun tabanı', () => {
    it('en küçük ölçünün altına inmez ve NEDEN durduğunu söyler', () => {
        /*
            Kullanıcı uzun kenarı 1280'e çekiyor ama slot 1200 × 400 ve 3:1
            istiyor. Sessizce 1280'e inmek dosyayı kullanılamaz yapardı;
            sessizce 1200'de durmak ise kaydırıcıyı bozuk gösterirdi.
        */
        render(
            <MediaOptimizeStep
                source={{ width: 4000, height: 3000 }}
                minimum={{ width: 1200, height: 400 }}
                aspect="3:1"
                maxEdge={1000}
                onMaxEdge={NOOP}
                beforeBytes={8_000_000}
                afterBytes={null}
                busy={false}
            />,
        );

        expect(screen.getByText(/at least 1200 × 400 pixels/i)).toBeInTheDocument();
        expect(screen.getByText(/1200 × 900 pixels will be sent/i)).toBeInTheDocument();
    });

    it('uzun kenar kaydırıcısı kontrol yüksekliğini taşır', () => {
        // Dokunmatik hedef kuralı: kaydırıcı parmakla sürüklenecek.
        const onMaxEdge = vi.fn();

        render(
            <MediaOptimizeStep
                source={{ width: 4000, height: 3000 }}
                minimum={{ width: 0, height: 0 }}
                aspect={null}
                maxEdge={2560}
                onMaxEdge={onMaxEdge}
                beforeBytes={8_000_000}
                afterBytes={null}
                busy={false}
            />,
        );

        const slider = screen.getByLabelText(/longest side/i);

        expect(slider).toHaveClass('min-h-[var(--control-height)]');
    });
});
