import type { Meta, StoryObj } from '@storybook/react-vite';

import { ImageCropField } from './ImageCropField';

/**
 * Kırpma çerçevesi ürüne girmeden görülemiyordu (FF-129) — ve tam da bu
 * yüzden merkez kırpmanın yanlış kareyi seçtiği yıllarca fark edilmedi.
 *
 * Örnek görüntü bir `data:` SVG'dir: hikâye ağa çıkmaz ve kare, çerçevenin
 * neyi kestiğini tek bakışta gösterir — sol üstte bir işaret vardır, merkez
 * kırpma onu her zaman keser.
 */
const SAMPLE =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="2400" height="1200">
            <rect width="2400" height="1200" fill="#ededf4"/>
            <circle cx="300" cy="300" r="180" fill="#ffb900"/>
            <rect x="1900" y="800" width="380" height="300" fill="#26224a"/>
            <text x="1100" y="640" font-family="sans-serif" font-size="120" fill="#080616">2400 × 1200</text>
        </svg>`,
    );

const meta: Meta<typeof ImageCropField> = {
    title: 'Surface/Workspace/ImageCropField',
    component: ImageCropField,
    parameters: { layout: 'padded' },
    args: {
        objectUrl: SAMPLE,
        source: { width: 2400, height: 1200 },
        mimeType: 'image/png',
        onCropped: () => undefined,
    },
    decorators: [
        (Story) => (
            <div className="max-w-[40rem] bg-canvas p-[var(--space-4)]">
                <Story />
            </div>
        ),
    ],
};

export default meta;

type Story = StoryObj<typeof ImageCropField>;

/** Kapak görseli: 3:1, yani kaynağın yarısından fazlası kesilir. */
export const WideCover: Story = {
    args: { aspect: '3:1', minimum: { width: 1200, height: 400 } },
};

/** Kare slot: kaynağın kenarları kesilir, seçim asıl burada önemlidir. */
export const Square: Story = {
    args: { aspect: '1:1', minimum: { width: 512, height: 512 } },
};

/**
 * Oransız slot (logo): kırpma yoktur ve araç HİÇ çizilmez — boş bir kutu
 * yerine hiçbir şey, çünkü seçilecek bir şey yok.
 */
export const NoAspect: Story = {
    args: { aspect: null, minimum: { width: 512, height: 512 } },
};
