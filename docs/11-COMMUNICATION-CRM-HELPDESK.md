# 11 — Communication, CRM & Helpdesk

**PLANNING ONLY.**

## 1. E-posta

**Default**: PHP native mail compatibility (kullanıcı tercihi olarak belirtilmiş)
— ancak bu, "test edilmeden güvenilir" anlamına gelmez: **health check ve
delivery test zorunludur** (gönderim başarısızlığı sessizce yutulmaz).

**Tam adapter desteği**: SMTP ve Mailgun (django-anymail'in Laravel karşılığı
olarak Laravel Mailer sürücüleri, kanıtlanmış).

Zorunlu e-posta mühendisliği: table-based HTML + plaintext alternatifi, inline
CSS (e-posta istemcileri modern CSS'i desteklemez), responsive tasarım,
SPF/DKIM/DMARC yapılandırması, `List-Unsubscribe` header, bounce/complaint
işleme, suppression list (şikayet edenlere tekrar göndermeme), retry +
idempotency, template + preview sistemi.

## 2. SMS

- **Türkiye default candidate**: Netgsm OTP.
- **Global fallback**: Twilio Verify.
- **Ek aday**: Vonage / Verimor (değerlendirme aşamasında, henüz seçilmedi).
- "En ucuz" iddiası **doğrulanmadan kullanılmaz** — canlı teklif + TCO
  (Total Cost of Ownership) karşılaştırması olmadan maliyet kararı verilmez;
  bu tip iddialar `docs/16`'da açık karar maddesi olarak kalır.
- Zorunlu: E.164 numara formatı, anti-toll-fraud koruması, rate limiting, kod
  süresi (expiry), açık rıza (consent) kaydı.

## 3. Mini CRM

Contacts, consent, timeline, segments, tasks, notes. Tenant-yerel (bir
restoranın kendi müşteri/lead'leri) ile platform-seviyeli CRM (`docs/02`
Sales/CRM Operator rolü) **ayrı veri alanlarıdır** — aynı tabloyu paylaşmazlar,
CORE-02 tenant izolasyonu burada da geçerlidir.

## 4. Helpdesk / Ticket

Ticket queue'ları, status, SLA, atama (assignment), eskalasyon, ek dosya,
knowledge base. **Tenant/platform sınırı**: bir tenant yalnız kendi ticket'larını
görür; platform Support Agent rolü (`docs/02`) sınır ötesi görünürlüğe sahiptir
ama bu görünürlük audit'e yazılır.

## 5. Kanonik sahiplik

E-posta/SMS/CRM/Helpdesk mimarisi burada kanoniktir. Bu dört alanın modül
spec'leri `modules/mini-crm.md` ve `modules/helpdesk-tickets.md`'de detaylandırılır;
e-posta/SMS altyapısı ayrı bir "product modülü" değil, CORE-14 Notifications'ın
adaptör katmanı olarak `modules/core-notifications.md`'de yaşar.
