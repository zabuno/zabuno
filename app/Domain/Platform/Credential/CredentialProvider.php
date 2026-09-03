<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Platform kasasının tanıdığı SAĞLAYICILAR — ve her birinin alan şeması.
 *
 * Neden burada bir enum: kasaya rastgele bir sağlayıcı adı/rastgele alan
 * YAZILAMAZ. Panelin gösterdiği alanlar, doğruladığı alanlar ve şifrelediği
 * alanlar hep bu tek kaynaktan gelir. Bir sağlayıcının hangi alanları
 * gerektirdiği koddadır; değeri değil.
 *
 * `openai`, `gemini`, `anthropic`, `kimi` ve `custom_endpoint` AI düzlemini
 * besler (`docs/51`), `mailgun` posta gönderimini (`docs/93`), `iyzico`
 * ödemeyi (P1-02 — kasa anahtarı saklar, ödeme akışının bağlanması ayrı
 * iştir).
 *
 * TÜKETİCİ ABONELİĞİ BURAYA GİREMEZ. Claude.ai Pro/Max ya da ChatGPT
 * Plus/Pro girişi hiçbir koşulda üretim kimlik bilgisi değildir
 * (`modules/ai-provider-account-vault.md` §Tüketici abonelik yasağı) — ve
 * bu yalnız bir kural değil, ŞEMANIN kendisi: aşağıda e-posta/parola/
 * oturum çerezi alacak bir alan yoktur, dolayısıyla böyle bir giriş
 * fiziksel olarak kaydedilemez.
 */
enum CredentialProvider: string
{
    case Mailgun = 'mailgun';
    case Iyzico = 'iyzico';
    case OpenAi = 'openai';
    case Gemini = 'gemini';

    /*
        FAZ 3 (`docs/95`) — sahibin sorduğu liste üç FARKLI biçimde oturur.

        Anthropic ve Kimi kendi başlarına birer sağlayıcıdır; doktrin ikisini
        de adıyla anıyor (`docs/51` §3.2, `docs/14` §2).

        Qwen ise kendi case'ini ALMAZ: doktrin onu `local`/self-host/
        OpenAI-uyumlu-uç-nokta sınıfına koyar (`docs/51` §3.2, §4.5). Onu
        "Gemini gibi" bir sağlayıcı yapmak, o hesabın sağlık ve kota
        davranışının OpenAI-uyumlu olduğunu VARSAYMAK olurdu — ama uyumluluk
        garanti değildir (`docs/51` §4.5: "tam uyumluluk varsayılmaz").
        Doğru model genel bir "özel uç nokta" sınıfıdır; superadmin Qwen'in
        (ya da vLLM/Ollama/başka bir OpenAI-uyumlu sunucunun) kendi adresini
        `base_url` alanına yazar.
    */
    case Anthropic = 'anthropic';
    case Kimi = 'kimi';
    case CustomEndpoint = 'custom_endpoint';

    /**
     * Bu sağlayıcının alanları — sır/düz sınıfıyla.
     *
     * `default` yalnız DÜZ alanlar içindir: bir sırrın varsayılanı olmaz.
     *
     * @return list<CredentialField>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Mailgun => [
                new CredentialField('domain', secret: false, required: true),
                new CredentialField('secret', secret: true, required: true),
                new CredentialField('endpoint', secret: false, required: false, default: 'api.mailgun.net'),
            ],
            self::Iyzico => [
                new CredentialField('api_key', secret: true, required: true),
                new CredentialField('secret_key', secret: true, required: true),
                new CredentialField('base_url', secret: false, required: false, default: 'https://sandbox-api.iyzipay.com'),
            ],
            self::OpenAi => [
                new CredentialField('api_key', secret: true, required: true),
                new CredentialField('base_url', secret: false, required: false, default: 'https://api.openai.com/v1'),
                new CredentialField('organization', secret: false, required: false),
                new CredentialField('project', secret: false, required: false),
            ],
            self::Gemini => [
                new CredentialField('api_key', secret: true, required: true),
                new CredentialField('base_url', secret: false, required: false, default: 'https://generativelanguage.googleapis.com'),
            ],
            self::Anthropic => [
                new CredentialField('api_key', secret: true, required: true),
                new CredentialField('base_url', secret: false, required: false, default: 'https://api.anthropic.com'),
            ],
            self::Kimi => [
                new CredentialField('api_key', secret: true, required: true),
                new CredentialField('base_url', secret: false, required: false, default: 'https://api.moonshot.ai/v1'),
            ],
            /*
                ÖZEL UÇ NOKTADA ZORUNLU OLAN ADRESTİR, ANAHTAR DEĞİL —
                sıralama da bilinçli: adres önce sorulur.

                Kendi barındırdığı bir sunucu (Qwen/vLLM/Ollama) çoğu
                kurulumda anahtarsız çalışır, ağ sınırında korunur; anahtarı
                zorunlu kılmak o kurulumu kasaya hiç giremez hâle getirirdi.
                Adresin VARSAYILANI ise olamaz: her kurulumun adresi kendine
                özgüdür ve uydurulmuş bir varsayılan, sessizce yanlış bir
                yere çağrı yapardı.
            */
            self::CustomEndpoint => [
                new CredentialField('base_url', secret: false, required: true),
                new CredentialField('api_key', secret: true, required: false),
            ],
        };
    }

    /** @return list<string> */
    public function fieldNames(): array
    {
        return array_map(static fn (CredentialField $f): string => $f->name, $this->fields());
    }

    public function field(string $name): ?CredentialField
    {
        foreach ($this->fields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function secretFieldNames(): array
    {
        return array_values(array_map(
            static fn (CredentialField $f): string => $f->name,
            array_filter($this->fields(), static fn (CredentialField $f): bool => $f->secret),
        ));
    }
}
