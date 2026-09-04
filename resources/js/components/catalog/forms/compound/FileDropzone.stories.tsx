import type { Meta, StoryObj } from '@storybook/react-vite';
import { FileDropzone } from './FileDropzone';

/**
 * Dosya seçme yüzeyi. Görsel yükleme ve menü CSV içe aktarma AYNI bileşeni
 * kullanır; değişen tek şey kabul edilen biçim ve önizlemedir.
 */
const meta: Meta<typeof FileDropzone> = {
    title: 'Compound/Forms/FileDropzone',
    component: FileDropzone,
    decorators: [
        (Story) => (
            <div className="max-w-[32rem] p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
    args: {
        label: 'Drop an image here, or choose a file',
        activeLabel: 'Release to add this image',
        hint: 'JPEG, PNG or WebP',
        chooseLabel: 'Choose a file',
        onSelect: () => {},
    },
};

export default meta;
type Story = StoryObj<typeof FileDropzone>;

export const Empty: Story = {};

/** CSV içe aktarma: aynı yüzey, başka biçim ve başka metin. */
export const CsvImport: Story = {
    args: {
        accept: '.csv,text/csv',
        label: 'Drop a CSV file here, or choose one',
        activeLabel: 'Release to import this file',
        hint: 'CSV only',
    },
};

/** Seçim yapılmış hâl: önizlemeyi çağıran verir. */
export const WithPreview: Story = {
    args: {
        replaceLabel: 'Choose a different file',
        preview: <p className="text-meta text-fg-muted">menu-export.csv — 12 KB</p>,
    },
};

/** Geçersiz seçim: kenarlık uyarı rengine geçer. */
export const Invalid: Story = { args: { invalid: true } };

/** Yükleme sürerken alan kapalıdır. */
export const Disabled: Story = { args: { disabled: true } };
