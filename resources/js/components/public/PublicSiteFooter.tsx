export function PublicSiteFooter() {
    return (
        <footer className="border-t border-gray-200 dark:border-gray-700">
            <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm text-fg-secondary">
                <span>&copy; {new Date().getFullYear()} Zabuno</span>
                <nav aria-label="Legal" className="flex flex-wrap gap-x-4 gap-y-2">
                    <a href="/terms" className="hover:underline">
                        Terms
                    </a>
                    <a href="/privacy" className="hover:underline">
                        Privacy
                    </a>
                    <a href="/kvkk" className="hover:underline">
                        KVKK
                    </a>
                </nav>
                <a href="/#contact" className="hover:underline">
                    Contact
                </a>
            </div>
        </footer>
    );
}
