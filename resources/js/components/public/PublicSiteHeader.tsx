export function PublicSiteHeader() {
    const isRoot = window.location.pathname === '/';
    const prefix = isRoot ? '' : '/';

    return (
        <header className="border-b border-gray-200 dark:border-gray-700">
            <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-4">
                <a href="/" className="text-xl font-semibold text-gray-900 dark:text-white">
                    Zabuno
                </a>
                <nav aria-label="Primary" className="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                    <a href={`${prefix}#features`} className="text-gray-700 hover:underline dark:text-gray-200">
                        Features
                    </a>
                    <a href={`${prefix}#how-it-works`} className="text-gray-700 hover:underline dark:text-gray-200">
                        How it works
                    </a>
                    <a href={`${prefix}#pricing`} className="text-gray-700 hover:underline dark:text-gray-200">
                        Pricing
                    </a>
                    <a href={`${prefix}#faq`} className="text-gray-700 hover:underline dark:text-gray-200">
                        FAQ
                    </a>
                    {isRoot ? (
                        <a href="#contact" className="text-gray-700 hover:underline dark:text-gray-200">
                            Contact
                        </a>
                    ) : null}
                </nav>
            </div>
        </header>
    );
}
