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
 * `openai` ve `gemini` AI düzlemini besler (`docs/51`), `mailgun` posta
 * gönderimini (`docs/93`), `iyzico` ödemeyi (P1-02 — kasa anahtarı saklar,
 * ödeme akışının bağlanması ayrı iştir).
 */
enum CredentialProvider: string
{
    case Mailgun = 'mailgun';
    case Iyzico = 'iyzico';
    case OpenAi = 'openai';
    case Gemini = 'gemini';

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
