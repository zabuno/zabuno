type PublicLegalPageProps = {
    title: string;
};

export function PublicLegalPage({ title }: PublicLegalPageProps) {
    return (
        <main id="main-content" className="mx-auto max-w-5xl px-4 py-10">
            <h1 className="text-3xl font-bold" style={{ fontSize: 'var(--font-size-display)' }}>
                {title}
            </h1>
            <p className="mt-6 max-w-2xl text-gray-600 dark:text-gray-300">
                This page is pending qualified legal review and is not yet published. It does not
                contain binding legal terms.
            </p>
        </main>
    );
}
