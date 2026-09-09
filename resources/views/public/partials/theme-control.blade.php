{{-- GÖRÜNÜM TERCİHİ — kabuğun her sunumunda AYNI denetim (C2).

     ── ÜÇ SEÇENEK, İKİ DEĞİL ────────────────────────────────────────────

     "Açık" ve "koyu" bir kullanıcı kararıdır; "sistem" ise bir kararı
     DEVRETMEKTİR — telefonu akşam kendiliğinden koyulaşan biri o davranışı
     korumak ister. İki durumlu bir anahtar o üçüncü hâli ifade edemez ve
     bir kez dokunulduğunda geri dönüş yolu bırakmaz.

     ── BETİK BAĞLANANA KADAR GÖRÜNMEZ ───────────────────────────────────

     Denetim sunucuda `hidden` doğar; `resources/js/site/theme.ts` onu
     bağladığı anda açar. Gerekçe `docs/118` E8'in ters yüzü: taban HTML
     çalışmak ZORUNDADIR, ve bu denetimin sunucu tarafında bir karşılığı
     YOKTUR (tema bir çerezde değil, tarayıcının kendi belleğinde yaşar).
     Betiksiz bir ziyaretçiye çalışmayan üç düğme göstermek, ona bir söz
     verip tutmamaktır; hiç göstermemek dürüsttür. Gezinti bundan
     etkilenmez: her bağlantı betiksiz de yerinde durur.

     ── AYNI ANDA BİRDEN ÇOK KOPYA ───────────────────────────────────────

     Üst çubukta, menü bölmesinde ve iki altbilgi sunumunda aynı parça
     duruyor. Betik hepsini tek bir durumdan besler: birinde yapılan seçim
     ötekilerde de aynı anda işaretlenir, çünkü tek kaynak `localStorage`
     ve tek uygulayıcı belge kökü. --}}
<div class="site-theme-control" data-theme-control role="group" aria-label="{{ $st['themeLabel'] }}" hidden>
    @foreach ([['light', 'sun'], ['dark', 'moon'], ['system', 'monitor']] as [$choice, $icon])
        <button
            type="button"
            class="dz-btn site-theme-option"
            data-theme-choice="{{ $choice }}"
            aria-pressed="false"
        ><x-phosphor :name="$icon" /><span>{{ $st['theme'.ucfirst($choice)] }}</span></button>
    @endforeach
</div>
