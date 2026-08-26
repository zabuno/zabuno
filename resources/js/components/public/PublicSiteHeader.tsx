import { Navbar } from 'flowbite-react';

const navbarTheme = {
    root: {
        base: 'border-b border-border bg-surface px-2 py-2.5',
        rounded: {
            on: 'rounded',
            off: '',
        },
        bordered: {
            on: 'border',
            off: '',
        },
        inner: {
            base: 'mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4',
            fluid: {
                on: '',
                off: '',
            },
        },
    },
};

export function PublicSiteHeader() {
    const isRoot = window.location.pathname === '/';
    const prefix = isRoot ? '' : '/';

    return (
        <Navbar
            data-testid="flowbite-navbar"
            aria-label="Primary"
            theme={navbarTheme}
            clearTheme={{ root: true }}
        >
            <a href="/" className="text-xl font-semibold text-fg">
                Zabuno
            </a>
            <div className="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                <a href={`${prefix}#features`} className="text-fg-secondary hover:underline">
                    Features
                </a>
                <a href={`${prefix}#how-it-works`} className="text-fg-secondary hover:underline">
                    How it works
                </a>
                <a href={`${prefix}#pricing`} className="text-fg-secondary hover:underline">
                    Pricing
                </a>
                <a href={`${prefix}#faq`} className="text-fg-secondary hover:underline">
                    FAQ
                </a>
                {isRoot ? (
                    <a href="#contact" className="text-fg-secondary hover:underline">
                        Contact
                    </a>
                ) : null}
            </div>
        </Navbar>
    );
}
