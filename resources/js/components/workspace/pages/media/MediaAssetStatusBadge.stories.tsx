import type { Meta, StoryObj } from '@storybook/react-vite';
import { MediaAssetStatusBadge } from './MediaAssetStatusBadge';

const meta: Meta<typeof MediaAssetStatusBadge> = {
    title: 'Surface/Workspace/Media/MediaAssetStatusBadge',
    component: MediaAssetStatusBadge,
};

export default meta;
type Story = StoryObj<typeof MediaAssetStatusBadge>;

export const Quarantined: Story = { args: { status: 'quarantined' } };
export const Scanning: Story = { args: { status: 'scanning' } };
export const Rejected: Story = { args: { status: 'rejected' } };
export const Accepted: Story = { args: { status: 'accepted' } };
export const Processing: Story = { args: { status: 'processing' } };
export const Ready: Story = { args: { status: 'ready' } };
export const Failed: Story = { args: { status: 'failed' } };
export const Unknown: Story = { args: { status: 'some-unrecognized-value' } };
