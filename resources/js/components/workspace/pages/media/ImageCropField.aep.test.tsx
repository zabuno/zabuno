import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { ImageCropField } from './ImageCropField';

/**
 * KIRPMA ARACININ SONUÇ SATIRI — kanonik teslim paketi tipografi kuralı:
 * "Sayılar `tabular-nums`".
 *
 * Restoran sahibinin yolculuğu: kapak fotoğrafında tabağı ortalamak için
 * yakınlaştırma çubuğunu SÜRÜKLER ve altındaki "şu ölçüde yüklenecek" satırı
 * her karede yeniden yazılır. Orantılı rakamlarda "1180" ile "1181" farklı
 * genişlikte çizilir; satır sürükleme boyunca sağa sola oynar ve tam da
 * okunması gereken sayı okunamaz hâle gelir. `tabular-nums` sabit genişlikli
 * rakam verir: sayı değişir, satır durur.
 *
 * Bu satır `text-meta`nın MEŞRU kullanımıdır — bir ölçüdür, bir cümle değil.
 *
 * Araç FF-129 ile yeni geldi; burada yalnız GÖRSEL DİLİ AEP'e uydurulur,
 * kırpma davranışına dokunulmaz.
 */
describe('ImageCropField — ölçü satırı', () => {
    it('sonuç ölçüsü tabular-nums ile yazılır', () => {
        render(
            <ImageCropField
                objectUrl="blob:test"
                source={{ width: 2400, height: 1200 }}
                aspect="3:1"
                minimum={{ width: 600, height: 200 }}
                onCropped={vi.fn()}
                mimeType="image/jpeg"
            />,
        );

        const result = screen.getByText(/Will be uploaded as/);

        expect(result).toHaveClass('tabular-nums');
        expect(result).toHaveClass('text-meta');
    });
});
