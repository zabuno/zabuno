import type { StorybookConfig } from '@storybook/react-vite';

const config: StorybookConfig = {
    stories: [
        '../resources/js/components/storybook-demo/**/*.stories.tsx',
        '../resources/js/components/catalog/**/*.stories.tsx',
        '../resources/js/components/workspace/**/*.stories.tsx',
    ],
    addons: ['@storybook/addon-a11y'],
    framework: {
        name: '@storybook/react-vite',
        options: {},
    },
};

export default config;
