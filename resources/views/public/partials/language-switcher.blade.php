@php
    $siteLanguages = array_intersect_key(
        [
            'en' => \App\Support\Localization\Language::English->endonym(),
            'tr' => \App\Support\Localization\Language::Turkish->endonym(),
        ],
        array_flip((array) config('i18n.shipped_locales', [])),
    );
@endphp
<form method="POST" action="{{ route('public.language') }}" class="site-language-switcher">
    @csrf
    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
    <fieldset>
        <legend>
            @foreach ($siteLanguages as $code => $name)
                @unless ($loop->first) / @endunless
                <span lang="{{ $code }}">{{ $name }}</span>
            @endforeach
        </legend>
        <div class="site-language-options">
            @foreach ($siteLanguages as $code => $name)
                <button type="submit" name="language" value="{{ $code }}" lang="{{ $code }}"
                        class="dz-btn site-language-option" aria-pressed="{{ $lang->ui === $code ? 'true' : 'false' }}">
                    {{ $name }}
                </button>
            @endforeach
        </div>
    </fieldset>
</form>
