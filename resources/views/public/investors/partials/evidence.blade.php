{{-- KANIT SATIRI — bir iddianın arkasındaki dosya (FF-251).

     Üç hâl var ve üçü de AYRI cümledir; ikisini birleştirmek, bilmediğimiz
     bir şeyi bildiğimiz gibi göstermek olurdu:

       1. Dosya var       → adresi yazılır. İddia denetlenebilir.
       2. Dosya YOK       → satır bunu söyler. Kanıt adresi, işaret ettiği
                            dosya silindiğinde kanıt olmaktan çıkar ve sessiz
                            kalması iddiayı ayakta tutardı.
       3. Dosya beklenmez → bir YOKLUĞUN işaret edecek dosyası yoktur
                            (sınırlar). Boş bırakmak "unutulmuş" gibi okunur;
                            yazmak, kararı görünür kılar.

     Parça olarak durmasının sebebi kabuğun kendi kuralı (`docs/100` §2): üç
     listede üç kez kopyalanmış bir koşul, ilk bakımda ikisi güncellenip biri
     unutulanla biterdi. --}}
@if ($row['source'] === null)
    <p class="site-ledger-note">{{ $st['investorsProductSourceNone'] }}</p>
@elseif ($row['present'])
    <p class="site-ledger-note">{{ $st['investorsProductSourceLabel'] }} {{ $row['source'] }}</p>
@else
    <p class="site-ledger-note">{{ $st['investorsProductSourceMissing'] }} {{ $row['source'] }}</p>
@endif
