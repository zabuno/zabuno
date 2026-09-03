<?php

declare(strict_types=1);

/**
 * AI Capability Plane yapılandırması — `docs/51` §3.
 *
 * Model adları BURADA yaşar, kodda değil. Sağlayıcılar katalogu bizim sürüm
 * döngümüzden bağımsız değiştirir; kodda sabit bir model adı, o ad emekliye
 * ayrıldığı gün üretimi durdurur ve düzeltmesi deploy gerektirir.
 *
 * Varsayılan: HİÇBİR bulut sağlayıcı bağlı değil. Ürün bu hâliyle tam
 * çalışır; bu bir kabul ölçütüdür (`docs/51` §3.6/1).
 */
return [

    /*
     * Küresel kapatma anahtarı. Tek ayarla bütün AI durur; ürün etkilenmez.
     */
    'enabled' => (bool) env('AI_ENABLED', false),

    /*
     * Dağıtım profili (`docs/51` §4.5).
     *
     * `shared-host` paylaşımlı barındırma içindir: orada bir yerel çıkarım
     * sidecar'ı VARSAYILAMAZ. Bu, deponun desteklediği gerçek bir kurulum
     * biçimidir ve yerel modeli her yerde varsaymak onu kırardı.
     */
    'profile' => env('AI_PROFILE', 'shared-host'),

    /*
     * Sağlayıcı → bağlantı hiyerarşisi.
     *
     * Numaralı anahtar dizisi DEĞİL: bir sağlayıcının birden çok resmi
     * bağlantısı (proje/workspace/service account) olabilir ve hesap sayısı
     * mimari bir sabit değil, yapılandırma verisidir.
     *
     * TÜKETİCİ ABONELİĞİ KİMLİK BİLGİSİ DEĞİLDİR: ChatGPT Plus/Pro ya da
     * Claude.ai aboneliği API kullanımını kapsamaz — API ayrı üründür ve
     * ayrı faturalanır (`modules/ai-provider-account-vault.md` §149).
     */
    'providers' => [

        'local' => [
            'connections' => [
                // `vps-ai` profilinde doldurulur; OpenAI-uyumlu uç nokta.
                // Tam uyumluluk VARSAYILMAZ; uyumluluk katmanı sınar.
            ],
        ],

        'google' => ['connections' => []],
        'openai' => ['connections' => []],
        'anthropic' => ['connections' => []],
    ],

    /*
     * Yetenek → aday model yönlendirmesi.
     *
     * Sıra sahibinin kararıdır (`docs/51` §3.3): yerel → Gemini → OpenAI →
     * Claude. Sebebi maliyet ve yetenek gerçekliği; tercih değil.
     *
     * Boş bir aday listesi, o yeteneğin `NoRoute` döndürmesi demektir —
     * sessizce çalışmaması değil.
     */
    'capabilities' => [
        'ocr.document' => ['candidates' => [], 'confidence_threshold' => 0.80],
        'menu.extract' => ['candidates' => [], 'confidence_threshold' => 0.90],
        'embedding.text' => ['candidates' => [], 'confidence_threshold' => 0.0],
        'classification.text' => ['candidates' => [], 'confidence_threshold' => 0.70],
    ],

    /*
     * Tenant başına aylık bütçe (kuruş).
     *
     * Global tavan DEĞİL: global bir tavan, bir tenant'ın tüketimiyle
     * diğerlerinin AI'sını kapatırdı. Dolduğunda AI durur, ÜRÜN DURMAZ —
     * medya kotasıyla aynı ilke (`docs/49` §10).
     */
    /*
     * OpenAI görüntü adaptörü (Vault Faz 5). Model adı KODDA değil burada:
     * sağlayıcı kataloğu bizim sürüm döngümüzden bağımsız değişir. Anahtar
     * burada YOK — o kasada (`platform_credentials`) ya da env'de.
     */
    'openai' => [
        'vision_model' => env('AI_OPENAI_VISION_MODEL', 'gpt-4o-mini'),
        'request_timeout' => (int) env('AI_OPENAI_TIMEOUT', 60),
    ],

    /*
     * Gemini görüntü adaptörü — `docs/96` (Faz 2, ÖNCELİKLİ). `docs/51`
     * §4b.1 görme zincirini Gemini→OpenAI→Claude sıralıyor; bu yüzden
     * `AppServiceProvider` binding'i Gemini'yi OpenAI'dan önce dener.
     */
    'gemini' => [
        'vision_model' => env('AI_GEMINI_VISION_MODEL', 'gemini-flash-latest'),
        // Şemaya bağlı metin üretimi (ürün açıklaması, çeviri taslağı) —
        // `docs/96` Faz 2. Görüntü modeliyle aynı aile ama ayrı env anahtarı:
        // ikisi farklı günlerde farklı sürüme yükseltilebilir.
        'text_model' => env('AI_GEMINI_TEXT_MODEL', 'gemini-flash-latest'),
        // Taksonomi yinelenen-terim tespiti (`docs/96` Faz 2). `docs/51`
        // §4.4 yerel-first şart koşuyor ama `ai-local` sidecar bugün yok
        // (§3.5) — bu geçici bir bulut yedeği, kalıcı mimari karar değil.
        'embedding_model' => env('AI_GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
        'request_timeout' => (int) env('AI_GEMINI_TIMEOUT', 60),
    ],

    /*
     * Anthropic (Claude) metin adaptörü — `docs/96` Faz 3.
     *
     * `max_tokens` burada YAŞAR çünkü Messages API'de zorunlu bir alandır;
     * atlanırsa 400 döner ve bu hesabın değil bizim hatamız olurdu.
     */
    'anthropic' => [
        'text_model' => env('AI_ANTHROPIC_TEXT_MODEL', 'claude-sonnet-5'),
        'max_tokens' => (int) env('AI_ANTHROPIC_MAX_TOKENS', 1024),
        'request_timeout' => (int) env('AI_ANTHROPIC_TIMEOUT', 60),
    ],

    /*
     * Kimi ve ÖZEL UÇ NOKTA — ikisi de OpenAI-uyumlu `/chat/completions`
     * konuşur (`docs/96` Faz 3).
     *
     * `custom_endpoint`'in model adı BOŞ başlar ve bu bilinçlidir: sistem o
     * sunucuda hangi modelin çalıştığını bilemez, superadmin söylemek
     * zorundadır. Uydurulmuş bir varsayılan, çalışmayan bir model adıyla
     * sessizce 404 alırdı.
     */
    'kimi' => [
        'text_model' => env('AI_KIMI_TEXT_MODEL', 'kimi-k2-0905-preview'),
        'request_timeout' => (int) env('AI_KIMI_TIMEOUT', 60),
    ],

    'custom_endpoint' => [
        'text_model' => env('AI_CUSTOM_ENDPOINT_TEXT_MODEL', ''),
        'request_timeout' => (int) env('AI_CUSTOM_ENDPOINT_TIMEOUT', 60),
    ],

    /*
     * Model başına fiyat (1.000.000 token başına, KURUŞ). Bütçe bu tablodan
     * türetilen maliyetle düşer; ayarlanmazsa maliyet 0 yazılır ve bütçe
     * yalnız aç/kapa görevi görür (miktar bazlı değil). Fotoğraf okuma
     * İNSAN TETİKLİDİR (onay hattı), o yüzden kontrolsüz döngü riski düşüktür;
     * yine de gerçek tavan için buraya fiyat girilmeli.
     */
    'pricing' => [
        // 'gpt-4o-mini' => ['input_per_million' => 0, 'output_per_million' => 0],
    ],

    'budget' => [
        'monthly_minor_per_tenant' => (int) env('AI_BUDGET_MONTHLY_MINOR', 0),
    ],

    /*
     * Prompt'a girmeden temizlenecek alan adları (`docs/51` UNK-04).
     *
     * `dataLayer` yasağıyla aynı gerekçe: sağlayıcıya giden veri geri
     * alınamaz.
     */
    'redact_fields' => ['email', 'phone', 'password', 'token', 'address', 'tax_id'],
];
