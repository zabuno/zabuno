## Anahtar sorunun cevabı: Form ve data table için doğru tasarım disiplini

**"Doğru"yu belirleyen kriterler** — bir disiplin şu altı testi geçiyorsa admin panelde kullanılabilir demektir:

1. **Tarama hızı:** Kullanıcı bir data table'da saniyeler içinde satır/değer bulabiliyor mu? Dekoratif her katman (gölge, blur, doku) tarama hızını düşürür.
2. **Kontrast ve erişilebilirlik:** Metin/arkaplan oranı her state'te ve her iki temada min 4.5:1 kalıyor mu?
3. **Render maliyeti:** 10.000 satırlık sanallaştırılmış tabloda scroll 60fps kalıyor mu? `backdrop-filter` (blur) gibi GPU-ağır efektler burada elenir.
4. **State iletişimi:** Hover, focus, selected, error, disabled, loading her hücrede ve her alanda ayırt edilebiliyor mu?
5. **Yoğunluk esnekliği:** Aynı bileşen comfortable/compact/dense modlarına token değişimiyle geçebiliyor mu?
6. **i18n dayanıklılığı:** Uzun Almanca kelime, RTL Arapça ve farklı sayı/tarih formatlarında layout kırılmıyor mu?

**Disiplinlerin bu kriterlere göre durumu:**

Kısa açıklama önce, tablo sonra: senin seçtiğin **Flat 2.0 + Card UI kombinasyonu form/table için zaten doğru ana disiplin.** Glassmorphism ise içerik yüzeyi disiplini değil, sınırlı bir katman efektidir.

| Disiplin | Ne yapar | Ne yapmaz | Karar |
|---|---|---|---|
| **Flat 2.0 (semi-flat)** | Hiyerarşiyi renk + tipografi + boşlukla kurar; gölgeyi yalnızca katman ayrımına (dropdown, modal, sticky header) saklar | Dekoratif derinlik, doku, gradient yığını yapmaz | **Ana disiplin — evet** |
| **Card UI** | Form section'larını ve KPI'ları gruplar, mobilde tablonun kart listesine dönüşmesini sağlar | Tablo satırlarını kartlaştırmaz (dense modda alan israfı) | **Container disiplini — evet, sınırıyla** |
| **Glassmorphism** | Geçici katmanlarda (command palette, side-sheet arkası) "üstte olma" hissi verir | Öngörülebilir kontrast vermez; blur, sanallaştırılmış tabloda jank üretir | **Form/table yüzeyinde hayır; overlay'de opsiyonel, bende önerim scrim'de bile düz yarı saydam katman** |
| **Neumorphism / Soft UI** | Yumuşak estetik verir | 4.5:1 kontrastı ve net state ayrımını veremez | **Hayır** |

Senin durumundaki pratik ayrımlar:

**Güvenli olan:** Flat 2.0 tabanı, token-driven density (36/44/52px satır yüksekliği), gölgesiz kartlar, derinliğin yalnız overlay katmanında kullanılması. IBM Carbon, SAP Fiori Horizon ve Atlassian'ın 2024+ hattı tam olarak budur — enterprise doğru yaklaşım da bu.

**Riskli olan:** Glassmorphism'i veri yüzeyine taşımak, zebra çizgili tablo (dense modda görsel gürültü; onun yerine 1px border + hover dolgusu), 16px altı metin, ve sarı (#FFB900) üzerine beyaz metin — sarı üzerinde metin her zaman koyu (#080616) olmalı, yoksa kontrast çöker. Sarıyı yalnız primary CTA ve vurguya ayır; tablo içi durum renkleri parlement mavisi + semantik yeşil/kırmızı olsun.

**2030/2035 vizyonuna bağ:** Adaptive AI hedefin görsel disiplini değil, mimariyi belirler. Form artık "insanın doldurduğu alan seti" olmaktan çıkıp "AI ajanının önerdiği, insanın onayladığı yüzey"e dönüşüyor; tablo ise konuşulabilir bir veri yüzeyine. Bunun tasarım karşılığı: davranış katmanını headless tut (bileşen mantığı stil içermez), stili tamamen token/CSS variable üzerinden ver. Böylece 2030'da görsel dil değişse bile form/tablo mantığı ve ajan entegrasyonu yeniden yazılmaz. Bu ilke, aşağıdaki prompt setinin omurgasıdır.

Figma MCP ve Storybook MCP için kopyala-yapıştır prompt setini ayrı dosyaya koydum — token temeli, form kiti, data table, hi-fi prototip (Figma tarafı) ve token senkronu, story'ler, a11y/i18n testleri, CI kalite kapıları (Storybook tarafı), hepsi senin kısıtlarınla (Roboto 400+, max 0.5rem radius, 320px-first, dark #080616) gömülü:Dosyada kullanım sırası da var: önce A1 (Figma token temeli), sonra bileşenler, sonra Storybook senkronu. İstersen bir sonraki adımda parlement mavisinin kesin hex'ini birlikte sabitleyip kontrast matrisini çıkarabiliriz.
