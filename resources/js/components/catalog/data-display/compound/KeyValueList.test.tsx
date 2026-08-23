import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { KeyValueList } from './KeyValueList';

describe('KeyValueList', () => {
    it('renders each entry label and value', () => {
        render(
            <KeyValueList
                entries={[
                    { key: 'name', label: 'Name', value: 'Ada Lovelace' },
                    { key: 'role', label: 'Role', value: 'Owner' },
                ]}
            />,
        );
        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
        expect(screen.getByText('Role')).toBeInTheDocument();
        expect(screen.getByText('Owner')).toBeInTheDocument();
    });

    it('composes a Divider between entries but not before the first one', () => {
        render(
            <KeyValueList
                entries={[
                    { key: 'a', label: 'A', value: '1' },
                    { key: 'b', label: 'B', value: '2' },
                    { key: 'c', label: 'C', value: '3' },
                ]}
            />,
        );
        expect(screen.getAllByRole('separator')).toHaveLength(2);
    });

    it('renders no divider for a single entry', () => {
        render(<KeyValueList entries={[{ key: 'a', label: 'A', value: '1' }]} />);
        expect(screen.queryByRole('separator')).not.toBeInTheDocument();
    });
});
