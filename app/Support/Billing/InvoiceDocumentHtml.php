<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Application\Billing\Dto\Invoice;
use App\Domain\Billing\InvoiceKind;
use App\Domain\Legal\CompanyProfile;
use App\Domain\Money\MoneyFormatter;

/**
 * Belgenin KÂĞIT düzeni (docs/130 §K6).
 *
 * Ekran düzeninden ayrı bir kiptir ve bilerek ayrıdır: paneldeki liste yan
 * yana kaydırılabilir bir tablodur, kâğıtta kaydırma diye bir şey yoktur —
 * ekranda düzgün duran bir tablo A4'te taşar. Burada sütun sayısı sabit ve
 * azdır, uzun adres sarar, hiçbir şey yatayda kaymaz.
 *
 * Metin belgenin kendi kaynak dilindedir (İngilizce) ve katalogda değildir
 * — `LegalDocument` ile aynı karar: belge bir arayüz yüzeyi değil, bir
 * belgedir.
 *
 * Girilmemiş satıcı alanı "not yet provided" olarak görünür
 * (`CompanyProfile::NOT_PROVIDED`); uydurulmaz. Belge ayrıca ne OLMADIĞINI
 * söyler: bu bir e-Arşiv / e-Fatura değildir.
 */
final class InvoiceDocumentHtml
{
    private const NOT_AN_EARCHIVE = 'This document is Zabuno\'s own accounting record of a collected payment. '
        .'It is not an e-Arşiv or e-Fatura document and it has not been submitted to any tax authority or integrator.';

    private const SELLER_INCOMPLETE = 'The issuing company details below are incomplete: the fields marked '
        .'"not yet provided" have not been entered yet. This document is not a complete commercial invoice '
        .'until they are.';

    public static function build(Invoice $invoice): string
    {
        $title = $invoice->kind === InvoiceKind::CreditNote ? 'Credit note' : 'Invoice';
        $amount = MoneyFormatter::format($invoice->amountMinor, $invoice->currency);

        $sellerRows = self::definitionRows([
            'Company' => $invoice->seller['legal_name'],
            'Address' => $invoice->seller['address'],
            'Tax office' => $invoice->seller['tax_office'],
            'Tax number' => $invoice->seller['tax_number'],
            'MERSIS' => $invoice->seller['mersis'],
            'E-mail' => $invoice->seller['email'],
            'Phone' => $invoice->seller['phone'],
        ]);

        $buyerRows = self::definitionRows([
            'Company' => $invoice->buyer['legal_name'],
            'Address' => $invoice->buyer['address'].', '.$invoice->buyer['city'].' '.$invoice->buyer['country'],
            'Tax office' => $invoice->buyer['tax_office'],
            'Tax number' => $invoice->buyer['tax_number'],
            'E-mail' => $invoice->buyer['email'],
            'Phone' => $invoice->buyer['phone'],
        ]);

        $sellerNotice = $invoice->sellerIsComplete()
            ? ''
            : '<p class="notice">'.self::escape(self::SELLER_INCOMPLETE).'</p>';

        $lineLabel = ($invoice->planName === '' ? 'Subscription' : $invoice->planName)
            .' — '.$invoice->periodDays.' days of Zabuno subscription';

        $counterNotice = $invoice->kind === InvoiceKind::CreditNote
            ? '<p class="notice">'.self::escape(
                'This credit note reverses an earlier invoice. The original invoice is kept unchanged; '
                .'a fiscal record is never edited or deleted.'
            ).'</p>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
            .self::escape($title.' '.$invoice->documentNumber)
            .'</title><style>'.self::css().'</style></head><body>'
            .'<h1>'.self::escape($title).'</h1>'
            .'<table class="meta"><tr><th>Document number</th><td>'.self::escape($invoice->documentNumber).'</td></tr>'
            .'<tr><th>Issued at</th><td>'.self::escape($invoice->issuedAt).'</td></tr></table>'
            .$counterNotice
            .$sellerNotice
            .'<h2>Issued by</h2><table class="party">'.$sellerRows.'</table>'
            .'<h2>Issued to</h2><table class="party">'.$buyerRows.'</table>'
            .'<h2>Detail</h2>'
            .'<table class="lines"><thead><tr><th class="desc">Description</th><th class="num">Amount</th></tr></thead>'
            .'<tbody><tr><td class="desc">'.self::escape($lineLabel).'</td>'
            .'<td class="num">'.self::escape($amount).'</td></tr></tbody></table>'
            .self::totals($invoice, $amount)
            .'<p class="notice">'.self::escape(self::NOT_AN_EARCHIVE).'</p>'
            .'</body></html>';
    }

    private static function totals(Invoice $invoice, string $amount): string
    {
        if ($invoice->vatRateBasisPoints === null || $invoice->netMinor === null || $invoice->vatMinor === null) {
            /*
                KDV oranı yapılandırılmadı: bir oran VARSAYILMAZ ve sıfır
                yazılmaz. Belge yalnız tahsil edilen tutarı gösterir ve
                ayrımın neden olmadığını söyler.
            */
            return '<table class="totals"><tr><th>Total collected</th><td>'.self::escape($amount).'</td></tr></table>'
                .'<p class="notice">'.self::escape(
                    'No VAT rate is configured, so this document shows no tax breakdown. '
                    .'The amount above is the amount actually collected.'
                ).'</p>';
        }

        $rate = number_format($invoice->vatRateBasisPoints / 100, 2).'%';

        return '<table class="totals">'
            .'<tr><th>Net</th><td>'.self::escape(MoneyFormatter::format($invoice->netMinor, $invoice->currency)).'</td></tr>'
            .'<tr><th>VAT ('.self::escape($rate).')</th><td>'.self::escape(MoneyFormatter::format($invoice->vatMinor, $invoice->currency)).'</td></tr>'
            .'<tr><th>Total collected</th><td>'.self::escape($amount).'</td></tr>'
            .'</table>';
    }

    /** @param array<string, string|null> $fields */
    private static function definitionRows(array $fields): string
    {
        $rows = '';

        foreach ($fields as $label => $value) {
            $text = $value === null || trim($value) === '' ? CompanyProfile::NOT_PROVIDED : $value;
            $missing = $value === null || trim($value) === '' ? ' class="missing"' : '';
            $rows .= '<tr><th>'.self::escape($label).'</th><td'.$missing.'>'.self::escape($text).'</td></tr>';
        }

        return $rows;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A4 için ölçülü tipografi: gövde 10 pt, kenar boşluğu mPDF'in sayfa
     * ayarından gelir. `word-wrap` uzun adresin sayfadan taşmasını değil,
     * satır atlamasını sağlar — kâğıtta taşan metin geri getirilemez.
     */
    private static function css(): string
    {
        return 'body{font-family:sans-serif;font-size:10pt;color:#111;}'
            .'h1{font-size:16pt;margin:0 0 8pt;}'
            .'h2{font-size:11pt;margin:12pt 0 4pt;border-bottom:0.5pt solid #999;padding-bottom:2pt;}'
            .'table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            .'th,td{text-align:left;vertical-align:top;padding:2pt 4pt;word-wrap:break-word;}'
            .'table.meta th,table.party th{width:32%;font-weight:normal;color:#555;}'
            .'table.lines th{border-bottom:0.5pt solid #999;}'
            .'table.lines td.desc,table.lines th.desc{width:70%;}'
            .'table.lines td.num,table.lines th.num{width:30%;text-align:right;}'
            .'table.totals{margin-top:6pt;}'
            .'table.totals th{width:70%;text-align:right;font-weight:normal;}'
            .'table.totals td{width:30%;text-align:right;font-weight:bold;}'
            .'td.missing{color:#8a6d00;font-style:italic;}'
            .'p.notice{margin:8pt 0 0;font-size:8.5pt;color:#555;}';
    }
}
