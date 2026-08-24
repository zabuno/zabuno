import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { Tabs, type TabsProps } from './Tabs';

function ControlledTabs(args: TabsProps) {
    const [selectedKey, setSelectedKey] = useState(args.selectedKey);
    return <Tabs {...args} selectedKey={selectedKey} onChange={setSelectedKey} />;
}

const items = [
    { key: 'details', label: 'Details', panel: <p>Order details go here.</p> },
    { key: 'items', label: 'Items', panel: <p>Line items go here.</p> },
    { key: 'history', label: 'History', disabled: true, panel: <p>History go here.</p> },
];

const meta: Meta<typeof Tabs> = {
    title: 'Compound/Navigation/Tabs',
    component: Tabs,
    parameters: {
        docs: {
            description: {
                component:
                    'A controlled tablist/tabpanel pair. Does not compose a Micro/Navigation component directly — its tab affordance has selection semantics (aria-selected, roving tabindex) that NavLink does not model — but follows the same visual/keyboard contract as the rest of Micro/Navigation.',
            },
        },
    },
    render: (args) => <ControlledTabs {...args} />,
};

export default meta;
type Story = StoryObj<typeof Tabs>;

export const Default: Story = {
    args: { items, selectedKey: 'details', label: 'Order sections', onChange: () => {} },
};

export const RightToLeft: Story = {
    args: {
        items: [
            { key: 'details', label: 'التفاصيل', panel: <p>تفاصيل الطلب.</p> },
            { key: 'items', label: 'العناصر', panel: <p>عناصر الطلب.</p> },
        ],
        selectedKey: 'details',
        label: 'أقسام الطلب',
        onChange: () => {},
    },
    parameters: { direction: 'rtl' },
};
