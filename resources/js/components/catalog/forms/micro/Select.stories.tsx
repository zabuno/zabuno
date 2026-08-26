import type { Meta, StoryObj } from '@storybook/react-vite';
import { Select } from './Select';

const meta: Meta<typeof Select> = {
    title: 'Micro/Forms/Select',
    component: Select,
    // Çıplak bir `<select>`'in erişilebilir adı yoktur. Gerçek kullanımda adı
    // SelectField'ın Label'ı verir; micro tek başına gösterildiğinde bu bağlam
    // story tarafından sağlanmalıdır (Checkbox story'si de aynısını yapar),
    // yoksa tarama üründe var olmayan bir kusur bildirir.
    args: {
        'aria-label': 'Cuisine',
    },
};

export default meta;
type Story = StoryObj<typeof Select>;

const options = (
    <>
        <option value="">Choose a cuisine…</option>
        <option value="turkish">Turkish</option>
        <option value="italian">Italian</option>
        <option value="japanese">Japanese</option>
    </>
);

export const Default: Story = {
    args: { children: options },
};

export const Invalid: Story = {
    args: { invalid: true, children: options },
};

export const Disabled: Story = {
    args: { disabled: true, children: options },
};
