import type { Meta, StoryObj } from '@storybook/react-vite';
import { DemoFormCard } from './DemoFormCard';

/**
 * Composes: Compound/Form/TextField (two instances), each of which composes
 * Micro/Input + a native label. This story only renders static fixture data
 * and a no-op onSubmit — no fetch, no route, no business rule (§2a/§6 of
 * docs/35).
 */
const meta: Meta<typeof DemoFormCard> = {
    title: 'Macro/DemoFormCard',
    component: DemoFormCard,
    args: {
        title: 'Restaurant profile',
        description: 'Basic info shown on the public menu header.',
        fields: [
            { id: 'name', label: 'Restaurant name', placeholder: 'e.g. Zabuno Kebap' },
            { id: 'city', label: 'City', helpText: 'Used for local search ranking.' },
        ],
    },
};

export default meta;
type Story = StoryObj<typeof DemoFormCard>;

export const Default: Story = {};

export const ValidationError: Story = {
    args: {
        fields: [
            {
                id: 'name',
                label: 'Restaurant name',
                errorText: 'Restaurant name is required.',
                defaultValue: '',
            },
            { id: 'city', label: 'City', helpText: 'Used for local search ranking.' },
        ],
    },
};
