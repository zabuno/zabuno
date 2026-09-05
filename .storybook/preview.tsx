import type { Preview } from '@storybook/react-vite';
import {
    withTheme,
    withDirection,
    withDensity,
    withFlowbiteTokenTheme,
    withPseudoLocale,
    themeAndDirectionGlobalTypes,
} from '../resources/js/storybook/decorators';
import '../resources/css/app.css';

type PreviewGlobalTypes = NonNullable<Preview['globalTypes']>;

const preview: Preview = {
    globalTypes: themeAndDirectionGlobalTypes as PreviewGlobalTypes,
    /*
        Storybook listedeki İLK decorator'ü EN İÇE koyar. `withPseudoLocale`
        bu yüzden başta: ölçüm dili yalnız story'nin kendi metnine uygulanır,
        yön/yoğunluk/tema sarmalayıcılarının kurduğu bağlamı bozmaz. Bayrak
        kapalıyken hiçbir şey yapmaz.
    */
    decorators: [withPseudoLocale, withFlowbiteTokenTheme, withDensity, withDirection, withTheme],
    parameters: {
        viewport: {
            options: {
                xs320: {
                    name: 'xs (320px)',
                    styles: { width: '320px', height: '640px' },
                },
            },
        },
        a11y: {
            test: 'error',
        },
    },
};

export default preview;
