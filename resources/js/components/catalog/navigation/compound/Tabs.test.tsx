import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Tabs } from './Tabs';

const items = [
    { key: 'details', label: 'Details', panel: <p>Details panel</p> },
    { key: 'items', label: 'Items', panel: <p>Items panel</p> },
    { key: 'history', label: 'History', disabled: true, panel: <p>History panel</p> },
];

describe('Tabs', () => {
    it('marks the selected tab with aria-selected', () => {
        render(
            <Tabs items={items} selectedKey="details" onChange={() => {}} label="Order sections" />,
        );
        expect(screen.getByRole('tab', { name: 'Details' })).toHaveAttribute(
            'aria-selected',
            'true',
        );
        expect(screen.getByRole('tab', { name: 'Items' })).toHaveAttribute(
            'aria-selected',
            'false',
        );
    });

    it('hides panels that are not selected', () => {
        render(
            <Tabs items={items} selectedKey="details" onChange={() => {}} label="Order sections" />,
        );
        expect(screen.getByText('Details panel')).toBeVisible();
        expect(screen.getByText('Items panel')).not.toBeVisible();
    });

    it('uses roving tabindex so only the selected tab is in the natural tab order', () => {
        render(
            <Tabs items={items} selectedKey="details" onChange={() => {}} label="Order sections" />,
        );
        expect(screen.getByRole('tab', { name: 'Details' })).toHaveAttribute('tabindex', '0');
        expect(screen.getByRole('tab', { name: 'Items' })).toHaveAttribute('tabindex', '-1');
    });

    it('moves selection with ArrowRight, skipping disabled tabs', async () => {
        const onChange = vi.fn();
        render(
            <Tabs items={items} selectedKey="details" onChange={onChange} label="Order sections" />,
        );
        screen.getByRole('tab', { name: 'Details' }).focus();
        await userEvent.keyboard('{ArrowRight}');
        expect(onChange).toHaveBeenCalledWith('items');
        await userEvent.keyboard('{ArrowRight}');
        expect(onChange).toHaveBeenLastCalledWith('details');
    });

    it('jumps to the first tab on Home and the last on End', async () => {
        const onChange = vi.fn();
        render(
            <Tabs items={items} selectedKey="items" onChange={onChange} label="Order sections" />,
        );
        screen.getByRole('tab', { name: 'Items' }).focus();
        await userEvent.keyboard('{End}');
        expect(onChange).toHaveBeenLastCalledWith('items');
        await userEvent.keyboard('{Home}');
        expect(onChange).toHaveBeenLastCalledWith('details');
    });

    it('selects a tab on click', async () => {
        const onChange = vi.fn();
        render(
            <Tabs items={items} selectedKey="details" onChange={onChange} label="Order sections" />,
        );
        await userEvent.click(screen.getByRole('tab', { name: 'Items' }));
        expect(onChange).toHaveBeenCalledWith('items');
    });
});
