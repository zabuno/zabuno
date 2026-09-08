{{-- Desktop only: all targets still originate in SiteNavigation. No invented destinations.
     A separate presentation keeps native mobile details intact, without forcing them open. --}}
@php
    $footerGroups = collect($nav['footer'])->keyBy('id');
    $primaryGroup = collect($nav['header'])->firstWhere('id', 'primary');
    $productGroup = $footerGroups->get('product');
    $companyGroup = $footerGroups->get('company');
    $accountGroup = $footerGroups->get('account');
    $companyItems = collect($companyGroup['items'] ?? []);
    $productItems = collect($productGroup['items'] ?? []);
    $desktopColumns = [
        [
            'id' => 'product',
            'label' => $productGroup['label'],
            'items' => collect($primaryGroup['items'] ?? [])->filter(fn ($item) => str_contains($item['href'], '#'))
                ->concat($productItems->reject(fn ($item) => $item['href'] === '/help'))->all(),
        ],
        [
            'id' => 'company',
            'label' => $companyGroup['label'],
            'items' => $companyItems->reject(fn ($item) => in_array($item['href'], ['/contact', '/trust', '/accessibility'], true))->all(),
        ],
        [
            'id' => 'help',
            'label' => $st['navHelp'],
            'items' => $productItems->filter(fn ($item) => $item['href'] === '/help')
                ->concat($companyItems->filter(fn ($item) => $item['href'] === '/contact'))->all(),
        ],
        [
            'id' => 'trust',
            'label' => $st['navTrust'],
            'items' => $companyItems->filter(fn ($item) => in_array($item['href'], ['/trust', '/accessibility'], true))->all(),
        ],
        $accountGroup,
    ];
@endphp
<div class="site-desktop-footer" data-desktop-footer>
    <div class="site-shell-inner">
        <section class="site-desktop-footer-invitation" aria-labelledby="desktop-footer-invitation">
            <div>
                <h2 id="desktop-footer-invitation">{{ $st['homeHeroHeading'] }}</h2>
                <p>{{ $st['homeHeroNote'] }}</p>
            </div>
            <nav class="site-desktop-footer-actions" aria-label="{{ $st['homeHeroActionsLabel'] }}">
                <a href="/register" data-emphasis="true">{{ $st['homeHeroRegister'] }}</a>
                <a href="/app">{{ $st['homeOpenApp'] }}</a>
            </nav>
        </section>

        <div class="site-desktop-footer-brand">
            <a href="/" class="site-desktop-footer-wordmark">{{ $st['brand'] }}</a>
            <p>{{ $st['footerTagline'] }}</p>
        </div>

        <div class="site-desktop-footer-columns">
            @foreach ($desktopColumns as $column)
                @if ($column && $column['items'] !== [])
                    <nav aria-label="{{ $column['label'] }}" data-footer-column="{{ $column['id'] }}">
                        <h2>{{ $column['label'] }}</h2>
                        <ul>
                            @foreach ($column['items'] as $item)
                                <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            @endforeach
        </div>

        @if ($nav['content'] !== [])
            <section class="site-desktop-footer-content" aria-labelledby="desktop-footer-content">
                <h2 id="desktop-footer-content">{{ $st['footerContentMenus'] }}</h2>
                <div class="site-desktop-footer-columns">
                    @foreach ($nav['content'] as $group)
                        <nav aria-label="{{ $group['label'] }}">
                            <h3>{{ $group['label'] }}</h3>
                            <ul>
                                @foreach ($group['items'] as $item)
                                    <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="site-desktop-footer-legal">
            @foreach ($nav['legal'] as $group)
                <nav aria-label="{{ $group['label'] }}">
                    <h2>{{ $group['label'] }}</h2>
                    <ul>
                        @foreach ($group['items'] as $item)
                            <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>

        <section class="site-desktop-footer-identity" aria-labelledby="desktop-footer-seller">
            <div>
                <h2 id="desktop-footer-seller">{{ $st['aboutSellerHeading'] }}</h2>
                @unless ($companyComplete)
                    <p data-footer-identity-warning>{{ $st['aboutIncompleteBody'] }}</p>
                @endunless
            </div>
            @include('public.partials.company-identity')
        </section>

        <div class="site-desktop-footer-bottom">
            <span>&copy; {{ now()->year }} {{ $st['brand'] }}</span>
            <a href="#main-content">{{ $st['footerBackToTop'] }}</a>
        </div>
    </div>
</div>
