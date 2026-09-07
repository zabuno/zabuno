@extends('public.layout')

@section('title', $st['contactHeading'])
@section('description', $st['contactLead'])

@section('content')
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-10">
        <h1 class="text-3xl font-bold">{{ $st['contactHeading'] }}</h1>

        @if (session('contact.sent'))
            {{-- Teyit EKRANDA. "Gönderildi" demeyen bir form, gönderilip
                 gönderilmediğini bilmeyen bir kullanıcı bırakır. --}}
            <div role="status" class="flex flex-col gap-2 rounded-lg border border-border p-4 text-fg">
                <p>{{ $st['contactSent'] }}</p>
                @if ($sentReference !== null)
                    {{-- REFERANS EKRANDA (FF-201): e-posta çıkmasa bile
                         numara elde kalır. Bal küpü gönderiminde referans
                         yoktur ve bu satır hiç çizilmez. --}}
                    <p class="font-semibold">{{ $sentReference }}</p>
                @endif
            </div>
        @endif

        <p class="text-fg-secondary">{{ $st['contactLead'] }}</p>

        {{-- SATICININ GERÇEK İLETİŞİM BİLGİSİ (FF-216). Form tek başına bir
             iletişim yolu değildir; adres, telefon ve e-posta `/about` ile
             AYNI kaynaktan gelir ve girilmemişse öyle yazar. --}}
        <section aria-labelledby="contact-identity-heading" class="flex flex-col gap-3">
            <h2 id="contact-identity-heading" class="text-xl font-bold">{{ $st['contactIdentityHeading'] }}</h2>
            @include('public.partials.company-identity')
        </section>

        <h2 class="text-xl font-bold">{{ $st['contactFormHeading'] }}</h2>

        @if ($commitment !== null)
            {{-- Yalnız yapılandırılmışsa (`docs/125` §3). Boşken bu satır
                 YOKTUR; yedek bir "en kısa sürede" cümlesi de yoktur. --}}
            <p class="text-fg-secondary">{{ $commitment }}</p>
        @endif

        @if ($errors->any())
            <ul role="alert" class="flex flex-col gap-1 text-fg-danger">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="/contact" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-1">
                {{-- Etiket ŞART: yer tutucu bir etiket değildir ve ekran
                     okuyucu onu alan adı olarak okumaz. --}}
                <label for="contact-name" class="font-medium">{{ $st['contactName'] }}</label>
                <input id="contact-name" name="name" type="text" required autocomplete="name"
                       value="{{ old('name') }}"
                       class="rounded-md border border-border bg-surface px-3 py-2">
            </div>

            <div class="flex flex-col gap-1">
                <label for="contact-email" class="font-medium">{{ $st['contactEmail'] }}</label>
                <input id="contact-email" name="email" type="email" required autocomplete="email"
                       value="{{ old('email') }}"
                       class="rounded-md border border-border bg-surface px-3 py-2">
            </div>

            <div class="flex flex-col gap-1">
                <label for="contact-message" class="font-medium">{{ $st['contactMessage'] }}</label>
                <textarea id="contact-message" name="message" rows="6" required maxlength="4000"
                          class="rounded-md border border-border bg-surface px-3 py-2">{{ old('message') }}</textarea>
            </div>

            {{-- BAL KÜPÜ: insan bunu görmez, dolayısıyla dolduramaz.
                 `aria-hidden` ve `tabindex="-1"`, ekran okuyucu ve klavye
                 kullanan bir insanın da yanlışlıkla doldurmasını engeller —
                 aksi hâlde tuzak, korumak istediği kişiyi yakalardı. --}}
            <div aria-hidden="true" class="hidden">
                <label for="contact-website">{{ $st['contactHoneypot'] }}</label>
                <input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit"
                    class="site-action self-start rounded-md border border-action bg-action px-4 py-2 font-semibold text-action-fg">
                {{ $st['contactSubmit'] }}
            </button>
        </form>
    </main>
@endsection
