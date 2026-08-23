import { AccessibleSeparator } from '../adapters/AccessibleSeparator';

export function PublicHomePage() {
    return (
        <main id="main-content" className="mx-auto max-w-5xl px-4 py-10">
            <section className="flex flex-col gap-6">
                <h1 className="text-3xl font-bold" style={{ fontSize: 'clamp(1.75rem, 1.4rem + 1.5vw, 2.5rem)' }}>
                    Run your restaurant&apos;s menu and workspace from one place
                </h1>
                <p className="max-w-2xl text-gray-600 dark:text-gray-300">
                    Zabuno gives your team a shared workspace to manage a restaurant&apos;s menu and
                    catalog, publish it as a stable QR-linked page, and keep it updated as things
                    change.
                </p>
                <nav aria-label="Account actions" className="flex flex-wrap gap-3">
                    <a
                        href="/app"
                        className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 forced-colors:border forced-colors:border-[ButtonText]"
                    >
                        Open workspace app
                    </a>
                    <a
                        href="/login"
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-700 forced-colors:border-[ButtonText]"
                    >
                        Log in
                    </a>
                    <a
                        href="/register"
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-700 forced-colors:border-[ButtonText]"
                    >
                        Create account
                    </a>
                </nav>
            </section>

            <AccessibleSeparator className="my-10" />

            <section id="features" aria-labelledby="features-heading" className="flex flex-col gap-6">
                <h2 id="features-heading" className="text-2xl font-bold">
                    Features
                </h2>
                <div className="grid gap-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(16rem, 1fr))' }}>
                    <div>
                        <h3 className="font-semibold">Restaurant &amp; workspace context</h3>
                        <p className="mt-1 text-gray-600 dark:text-gray-300">
                            Keep a restaurant&apos;s workspace, team, and settings organized in one
                            tenant-scoped place.
                        </p>
                    </div>
                    <div>
                        <h3 className="font-semibold">Menu &amp; catalog operations</h3>
                        <p className="mt-1 text-gray-600 dark:text-gray-300">
                            Create and edit menu items, categories, and catalog details from the
                            workspace app.
                        </p>
                    </div>
                    <div>
                        <h3 className="font-semibold">Publication &amp; stable QR</h3>
                        <p className="mt-1 text-gray-600 dark:text-gray-300">
                            Publish a menu to a stable, shareable page that a printed QR code can
                            keep pointing to.
                        </p>
                    </div>
                    <div>
                        <h3 className="font-semibold">Media intake &amp; analytics</h3>
                        <p className="mt-1 text-gray-600 dark:text-gray-300">
                            Media uploads go through quarantined media intake and review before
                            they are available, alongside basic usage analytics for the published
                            page.
                        </p>
                    </div>
                </div>
            </section>

            <AccessibleSeparator className="my-10" />

            <section id="how-it-works" aria-labelledby="how-it-works-heading" className="flex flex-col gap-6">
                <h2 id="how-it-works-heading" className="text-2xl font-bold">
                    How it works
                </h2>
                <ol className="flex flex-col gap-4">
                    <li>
                        <strong>Set up</strong> — complete your workspace and restaurant setup.
                    </li>
                    <li>
                        <strong>Build the menu</strong> — add categories, items, prices,
                        visibility, and allergens to your catalog.
                    </li>
                    <li>
                        <strong>Publish &amp; get a QR</strong> — publish the menu to a stable page
                        with a QR code.
                    </li>
                    <li>
                        <strong>Update anytime</strong> — edit the menu and the published page and
                        QR code stay the same.
                    </li>
                </ol>
            </section>

            <AccessibleSeparator className="my-10" />

            <section id="pricing" aria-labelledby="pricing-heading" className="flex flex-col gap-4">
                <h2 id="pricing-heading" className="text-2xl font-bold">
                    Pricing
                </h2>
                <p className="text-gray-600 dark:text-gray-300">
                    There are no published plan prices yet, and checkout is not available yet. We
                    will publish pricing here once it is ready.
                </p>
            </section>

            <AccessibleSeparator className="my-10" />

            <section id="faq" aria-labelledby="faq-heading" className="flex flex-col gap-4">
                <h2 id="faq-heading" className="text-2xl font-bold">
                    FAQ
                </h2>
                <dl className="flex flex-col gap-4">
                    <div>
                        <dt className="font-semibold">What is Zabuno?</dt>
                        <dd className="mt-1 text-gray-600 dark:text-gray-300">
                            A workspace app for managing a restaurant&apos;s menu and catalog and
                            publishing it to a stable QR-linked page.
                        </dd>
                    </div>
                    <div>
                        <dt className="font-semibold">Do I need an account to try it?</dt>
                        <dd className="mt-1 text-gray-600 dark:text-gray-300">
                            Yes, create an account or log in to open the workspace app.
                        </dd>
                    </div>
                    <div>
                        <dt className="font-semibold">Is pricing available?</dt>
                        <dd className="mt-1 text-gray-600 dark:text-gray-300">
                            Not yet — see the Pricing section above.
                        </dd>
                    </div>
                </dl>
            </section>

            <AccessibleSeparator className="my-10" />

            <section id="contact" aria-labelledby="contact-heading" className="flex flex-col gap-4">
                <h2 id="contact-heading" className="text-2xl font-bold">
                    Contact
                </h2>
                <p className="text-gray-600 dark:text-gray-300">
                    There is no connected contact form yet. Please check back later.
                </p>
            </section>
        </main>
    );
}
