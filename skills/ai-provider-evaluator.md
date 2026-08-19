# AI-provider-evaluator

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Periyodik (aylık) provider fiyat/politika taraması veya yeni bir provider
eklenmesi önerisi (`docs/14` §2, `docs/16` AI-01).

## Inputs
Mevcut feature×LLM matrisi + provider'ların güncel fiyat/politika sayfaları.

## Authority
Salt-okunur karşılaştırma — matrisi doğrudan değiştirmez, öneri üretir; matris
güncellemesi insan onayı gerektirir.

## Permitted tools/actions
Fiyat/latency/politika karşılaştırma, vendor-drift tespiti (önceki taramadan
bu yana değişen şartlar).

## Forbidden actions
Bir provider'ı otomatik olarak devreye alma/devre dışı bırakma (bu her zaman
insan kararı, `docs/14` §4 human-approval ruhuyla).

## Deterministic outputs / schema
```
{ provider, feature, price_changed: boolean, policy_changed: boolean, recommendation }
```

## Evidence
Karşılaştırılan fiyat/politika sayfalarının erişim tarihli özeti.

## Human approval
Matris güncellemesi Engineering + Finance onayı gerektirir (maliyet etkisi
nedeniyle).

## Failure / rollback
Bir provider'ın şartları kabul edilemez hale gelirse (örn. fiyat aşırı
artarsa) → fallback provider'a otomatik geçiş **önerilir**, otomatik
uygulanmaz.

## Eval cases
- Bir provider'ın fiyatının %50 arttığı senaryoda uyarı üretilmesi.
- Yeni bir OpenAI-compatible self-host seçeneğinin matrise eklenmesi önerisi.
- N-adet hesap/bağlantıdan (hard-code 1/2/3 değil) birinin şartları değiştiğinde
  yalnız o hesabın işaretlenmesi, diğer hesapların etkilenmemesi
  (`modules/ai-provider-account-vault.md`).
- Tenant BYOK hesabının provider-taraflı politika değişikliğinin, platform-owned
  hesap değerlendirmesinden **ayrı** raporlandığının testi (ikisi karıştırılmaz).

## Kapsam netleştirmesi — N-hesap ve BYOK

Bu skill provider **seviyesinde** (OpenAI/Claude/Gemini/Kimi/self-host vb.)
fiyat/politika taraması yapar; **hesap seviyesindeki** routing/health/rotation
kararı `skills/ai-account-routing.md`'nindir. Bir provider'ın kaç hesabı
olduğu (N, hard-code edilmez) ve bu hesapların platform-owned mi tenant BYOK
mu olduğu `modules/ai-provider-account-vault.md`'de yaşar — bu skill onu
tekrar etmez, yalnız provider-seviyeli sonuçları oraya besler.

## Phase
Stage 2 Post-MVP'den itibaren (AI Platform canlı olduğunda), aylık periyodik.
