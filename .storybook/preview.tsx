import type { Preview } from '@storybook/react-vite';
import {
    withTheme,
    withDirection,
    themeAndDirectionGlobalTypes,
} from '../resources/js/storybook/decorators';
import '../resources/css/app.css';

type PreviewGlobalTypes = NonNullable<Preview['globalTypes']>;

const preview: Preview = {
    globalTypes: themeAndDirectionGlobalTypes as PreviewGlobalTypes,
    decorators: [withDirection, withTheme],
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
