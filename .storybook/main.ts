import type { StorybookConfig } from '@storybook/react-vite';

const config: StorybookConfig = {
    stories: [
        '../resources/js/components/storybook-demo/**/*.stories.tsx',
        '../resources/js/components/catalog/**/*.stories.tsx',
        '../resources/js/components/workspace/**/*.stories.tsx',
        // Kimlik yüzeyi (FF-198): kayıt formu da 320 pikselde ölçülür.
        '../resources/js/components/auth/**/*.stories.tsx',
    ],
    addons: ['@storybook/addon-a11y'],
    framework: {
        name: '@storybook/react-vite',
        options: {},
    },
};

export default config;
