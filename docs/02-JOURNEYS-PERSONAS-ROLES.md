# 02 — Journeys, Personas & Roles

**PLANNING ONLY.**

## 1. Rol taksonomisi

### 1.1 Platform tarafı

| Rol | Kapsam | Sorumluluk | Not |
|---|---|---|---|
| Platform Owner | Tüm sistem | Platform ayarları, modüller, planlar, tenantlar, tüm kullanıcılar, sistem operasyonları, audit, admin yetkilendirme | En yüksek yetki; segregation-of-duties ile bile sınırlı sayıda kişi |
| Platform Admin | Günlük SaaS operasyonu | Restoran hesapları, kullanıcılar, abonelikler, destek, kullanım limitleri, askıya alma/açma | |
| Sales/CRM Operator | CRM, lead | Lead yönetimi, mini CRM (`docs/11-COMMUNICATION-CRM-HELPDESK.md`) | Eski Django rol setinden ("sales") kavramsal olarak korunmuştur; yeni ince taneli yetki modeliyle yeniden tanımlanır |
| Support Agent | Destek | Tenant görüntüleme, sorun geçmişi, destek notu, koşullu impersonation | Impersonation kuralları `docs/05` §Impersonation |
| Finance Operator | Para | Plan/abonelik takibi, ödeme kayıtları, manuel doğrulama, iade/iptal | `docs/09` ile birebir hizalı |
| Technical Operator | Sistem | Queue, scheduler, failed jobs, health check, log, cache/bakım | `docs/15` |
| Auditor (ince taneli, salt-okunur) | Governance | Audit log, consent, legal doküman versiyonları — **yazma yetkisi yok** | Segregation-of-duties gereği ayrı rol; Platform Admin ile birleştirilmez |
| Growth/Marketing Operator | SEO/content/analytics/campaign yüzeyi | SEO capability map, analytics/consent raporları, campaign/marketing içeriği yönetimi (`docs/12`, `modules/analytics-consent-tagging.md`, `modules/seo-search-discovery.md`) | Dar kapsamlı: tenant'ın menü/ürün/billing içeriğini keyfi değiştiremez, authz/secrets yönetimine erişemez; billing yetkisi yok |

### 1.2 Restoran (tenant) tarafı

| Rol | Kapsam | Sorumluluk |
|---|---|---|
| Account/Workspace Owner | Workspace | Abonelik sahibi, tüm ayarlar, takım, billing, menü, QR, analytics; son owner silinemez |
| Brand/Location Manager | Marka veya lokasyon | Günlük operasyon: menü, ürün, fiyat, QR, temel analitik, sınırlı takım yönetimi |
| Editor | İçerik | Menü, kategori, ürün, görsel, fiyat, görünürlük — billing/team/business settings'e **erişemez** |
| Şef/Mutfak (Kitchen) | Ürün doğruluğu | Alerjen/içerik/porsiyon bilgisi girişi ve onayı — yayınlama yetkisi yok (pilot doğrulaması bekliyor, `docs/16` OPS-04) |
| Analist | Salt-okunur analytics | Scan/analytics raporları, export — düzenleme yetkisi yok |
| Finans (tenant-yerel) | Fatura/ödeme | Kendi workspace faturaları, ödeme geçmişi — platform finans rolüyle karıştırılmaz |
| Destek Temsilcisi (tenant-yerel) | Müşteri/ticket | `docs/11` Helpdesk modülü kapsamında tenant-taraflı ticket yönetimi |
| Denetçi (tenant-yerel, salt-okunur) | Uyum | Kendi workspace audit/consent kayıtlarını görüntüler |

### 1.3 Public / restoran müşterisi

| Rol | Kapsam | Not |
|---|---|---|
| Logged-in Customer | Kayıtlı müşteri | Gelecekte loyalty/ordering (`OPT`) ile ilişkilendirilebilir profil |
| Guest | Kayıtsız | Varsayılan MVP davranışı — menüyü görür, sipariş/rezervasyon opsiyonel modüllerle gelir |
| Consented/Anonymous | Rıza durumu ayrık | Analytics/consent modeliyle bağlı (`docs/12-ANALYTICS-CONTENT-SEO.md` §Consent) |
| Restricted/Minor bağlamı | Yaşa özgü içerik (alkol vb.) | Yasal görünürlük kısıtı — ayrıntı `docs/16` LEG-04, mevzuat netleşene kadar varsayım olarak işaretli |

## 2. Yetki sınırları ve görev ayrımı (segregation of duties)

- Platform tarafı ile tenant tarafı rolleri **asla aynı yetki nesnesinde
  birleştirilmez** — Platform Admin bir workspace'in *içeriğini* değiştiremez,
  yalnız *operasyonel meta*sını (suspend, plan atama) yönetir (bkz. `docs/04`
  Product Operations notu: "müşteri içeriğini değiştirmek için değil, support ve
  teknik inceleme için").
- Impersonation dört şartla sınırlıdır: sebep girilir, süreli olur, ekranda banner
  gösterilir, tüm işlemler audit'e yazılır; billing/silme işlemleri impersonation
  sırasında engellenir (`docs/05` §Impersonation, `docs/10-ECA-WORKFLOW-TAXONOMY-STATE.md`
  ile hizalı onay akışı).
- Son Owner korunumu: bir workspace'in son Owner'ı silinemez/rolü indirilemez;
  transfer akışı zorunludur (`docs/05`).

## 3. Restoran hiyerarşisi

```
Account / Workspace
  └── Brand
        └── Location
              └── Floor / Area (upper, lower, indoor, garden, terrace, ...)
                    └── Section / Zone
                          └── Table
                                └── Seat
```

- **Workspace ≠ Restaurant**: bir workspace birden fazla Brand'e, bir Brand birden
  fazla Location'a sahip olabilir (`docs/05` §Tenant Model — eski dokümandaki
  "Tenant = Restaurant yapılmamalı" kararı doğrudan korunmuştur, ancak Laravel
  modeline yeniden yazılmıştır).
- MVP arayüzü tek workspace × tek brand × tek location × tek aktif ana menü
  sunar; veri modeli baştan 1:N ilişkiyi destekler (bkz. `docs/18-STAGE-01-MVP.md`).
- Floor/Area serbest metin + taksonomi karışımıdır (yukarı/aşağı/iç/bahçe/teras gibi
  örnekler sabit liste değil, `docs/04` Taxonomy CORE modülü ile yönetilir).

## 4. Gerçek onboarding ve bulk QR akışı (uçtan uca özet)

1. E-posta ile kayıt → doğrulama.
2. Workspace oluşturma → Brand → ilk Location; işletme profili alan seti
   `modules/core-tenancy.md` §Business profile contract'ta sahiplenilir
   (bu doküman yalnız akış sırasına link verir, alanları tekrar tanımlamaz).
3. Floor/Area/Section/Table hiyerarşisini kurma — **bulk wizard** ile: adet, adlandırma
   prefix/sequence/range, koltuk sayısı girilir (`docs/08` §Bulk Wizard).
4. İlk menü/kategori/ürün girişi (tekil veya CSV import, `OPT-07`).
5. Menü önizleme → yayınlama (draft/publication ayrımı, `docs/04`).
6. Bulk QR üretimi: her table için otomatik QR + destination ataması, tema/boyut
   seçimi, toplu PDF/print çıktısı (`docs/08`).
7. QR test etme (server-side scannability kontrolü + fiziksel test kaydı).
8. Aktivasyon event'i: restoran + menü + kategori + ürün + yayın + ilk dinamik QR +
   QR testi tamamlandığında müşteri "activated" sayılır (Time to First QR metriği,
   `docs/26`).

## 5. Kanonik sahiplik

Rol taksonomisi ve hiyerarşi burada tek kanonik kaynaktır. Yetkilendirme *motoru*
(RBAC/ABAC/ReBAC mekaniği, deny-by-default, PDP) `docs/05-DOMAIN-DATA-TENANCY-AUTH.md`'de
tanımlanır — buradaki roller o motorun *girdisidir*, tekrar tanımlanmaz.
