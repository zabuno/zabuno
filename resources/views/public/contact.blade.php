@extends('public.layout')

{{-- SAHNE (`docs/146` §10). Önsöz bandı `conduit` yüzünü taşıyor: iletişim
     sayfası bir MESAJIN gidip gelmesiyle ilgilidir ve veri hattı tam olarak
     bunu çizer — çizgi durur, ışık akar.

     Formun KENDİSİNDE hareket yok. Bir form doldurmak dikkat ister; alanın
     arkasında kayan bir katman, yazılan şeyi okumayı zorlaştırırdı. --}}
@section('title', $st['contactHeading'])
@section('description', $st['contactLead'])

@section('content')
    <main id="main-content" class="site-page site-contact-page">
        @include('public.partials.prologue', [
            'prologueHeading' => $st['contactHeading'],
            'prologueLead' => $st['contactLead'],
            'prologueVariant' => 'conduit',
        ])

        <div class="site-measure-page site-page-body site-contact-body">
            @if (session('contact.sent'))
                {{-- Teyit EKRANDA. "Gönderildi" demeyen bir form, gönderilip
                     gönderilmediğini bilmeyen bir kullanıcı bırakır. --}}
                <div role="status" class="site-notice">
                    <p>{{ $st['contactSent'] }}</p>
                    @if ($sentReference !== null)
                        {{-- REFERANS EKRANDA (FF-201): e-posta çıkmasa bile
                             numara elde kalır. Bal küpü gönderiminde referans
                             yoktur ve bu satır hiç çizilmez. --}}
                        <p class="font-semibold">{{ $sentReference }}</p>
                    @endif
                </div>
            @endif

            <div class="site-contact-layout">
                <section aria-labelledby="contact-form-heading" class="site-page-body site-contact-compose site-panel">
                    <h2 id="contact-form-heading" class="site-display-3">{{ $st['contactFormHeading'] }}</h2>

                    @if ($commitment !== null)
                        {{-- Yalnız yapılandırılmışsa (`docs/125` §3). Boşken bu satır
                             YOKTUR; yedek bir "en kısa sürede" cümlesi de yoktur. --}}
                        <p class="site-pricing-note">{{ $commitment }}</p>
                    @endif

                    @php
                        $contactErrors = [];
                        foreach (['name', 'email', 'message'] as $field) {
                            if ($errors->has($field)) {
                                $contactErrors[$field] = $errors->get($field);
                            }
                        }
                        $firstInvalidField = array_key_first($contactErrors);
                    @endphp
                    @if ($contactErrors !== [])
                        <ul role="alert" class="flex flex-col gap-1 text-fg-danger">
                            @foreach ($contactErrors as $field => $messages)
                                @foreach ($messages as $error)
                                    <li><a class="site-inline-action" href="#contact-{{ $field }}">{{ $error }}</a></li>
                                @endforeach
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="/contact" class="site-form">
                        @csrf

                        <div class="site-form-field">
                            {{-- Etiket ŞART: yer tutucu bir etiket değildir ve ekran
                                 okuyucu onu alan adı olarak okumaz. --}}
                            <label for="contact-name">{{ $st['contactName'] }}</label>
                            <input id="contact-name" name="name" @if ($errors->has('name')) aria-invalid="true" aria-describedby="contact-name-error" @endif
                                   @if ($firstInvalidField === 'name') autofocus @endif type="text" required autocomplete="name"
                                   value="{{ old('name') }}" class="site-input">
                            @error('name')
                                <p id="contact-name-error" class="text-fg-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="site-form-field">
                            <label for="contact-email">{{ $st['contactEmail'] }}</label>
                            <input id="contact-email" name="email" @if ($errors->has('email')) aria-invalid="true" aria-describedby="contact-email-error" @endif
                                   @if ($firstInvalidField === 'email') autofocus @endif type="email" required autocomplete="email"
                                   value="{{ old('email') }}" class="site-input">
                            @error('email')
                                <p id="contact-email-error" class="text-fg-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="site-form-field">
                            <label for="contact-message">{{ $st['contactMessage'] }}</label>
                            <textarea id="contact-message" name="message" @if ($errors->has('message')) aria-invalid="true" aria-describedby="contact-message-error" @endif
                                   @if ($firstInvalidField === 'message') autofocus @endif rows="6" required maxlength="4000"
                                      class="site-input">{{ old('message') }}</textarea>
                            @error('message')
                                <p id="contact-message-error" class="text-fg-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- BAL KÜPÜ: insan bunu görmez, dolayısıyla dolduramaz.
                             `aria-hidden` ve `tabindex="-1"`, ekran okuyucu ve klavye
                             kullanan bir insanın da yanlışlıkla doldurmasını engeller —
                             aksi hâlde tuzak, korumak istediği kişiyi yakalardı. --}}
                        <div aria-hidden="true" class="hidden">
                            <label for="contact-website">{{ $st['contactHoneypot'] }}</label>
                            <input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        <button type="submit" class="site-action site-cta self-start" data-emphasis="true">
                            {{ $st['contactSubmit'] }}
                        </button>
                    </form>
                </section>
                {{-- SATICININ GERÇEK İLETİŞİM BİLGİSİ (FF-216). Form tek başına bir
                     iletişim yolu değildir; adres, telefon ve e-posta `/about` ile
                     AYNI kaynaktan gelir ve girilmemişse öyle yazar. --}}
                <section aria-labelledby="contact-identity-heading" class="site-page-body site-contact-identity">
                    <h2 id="contact-identity-heading" class="site-display-3">{{ $st['contactIdentityHeading'] }}</h2>
                    @include('public.partials.company-identity')
                </section>
            </div>
        </div>
    </main>
@endsection
