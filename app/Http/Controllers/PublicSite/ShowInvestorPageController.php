<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\Contact\ResponseCommitment;
use App\Support\Localization\SiteText;
use App\Support\Site\InvestorDossier;
use App\Support\Site\PublicPlans;
use App\Support\Site\SiteShell;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * YATIRIMCI İLİŞKİLERİ — dört sayfa, tek denetleyici (FF-251).
 *
 * ═══ NEDEN DÖRT SAYFA ═══
 *
 * Sahibin isteği *"yatırımcı ilişkileri, pitch deck vb. bilgiler için
 * sayfalar"*dı. Dördün her biri BAŞKA bir soruya cevap veriyor ve hiçbiri
 * ötekinin kopyası değil:
 *
 *   · `/investors`          — durum: ürün nedir, nesi çalışıyor, nesi yok.
 *   · `/investors/product`  — envanter: her parça, her sınır, her kanıt dosyası.
 *   · `/investors/deck`     — sıra: aynı olgular, bir kez okunacak düzende.
 *   · `/investors/contact`  — yol: nereye yazılır ve muhatap kim.
 *
 * DÖRDÜ DE AYNI OLGULARI OKUR (`InvestorDossier`). Deck sayfası ayrı bir
 * anlatı DEĞİLDİR ve olamaz: ayrı yazılmış bir deck, ürün değiştiğinde
 * sessizce eskiyen ve kimsenin güncellemeyi hatırlamadığı ikinci bir gerçek
 * olurdu. PDF eki de bu yüzden yok — indirilen bir dosya, düzeltilemeyen bir
 * iddiadır.
 *
 * ═══ İKİNCİ BİR FORM ALTYAPISI YOK ═══
 *
 * `/investors/contact` bir form ÇİZMEZ. Sitede zaten bir iletişim akışı var
 * (`/contact`: doğrulama, bal küpü, hız sınırı, referans numarası, alındı
 * e-postası) ve ikinci bir form ikinci bir kuyruk, ikinci bir hız sınırı ve
 * bir gün birinin bakmayı unuttuğu ikinci bir kutu demekti. Sayfa NE
 * yazılacağını söyler ve var olan yola gönderir.
 *
 * ═══ VERİTABANI ═══
 *
 * Yalnız iki yerden: gezinti kütüğü (kabuk, hatada boş listeye düşer) ve
 * plan kataloğu (`PublicPlans`, hatada boş listeye düşer). Yatırımcı
 * olgularının hiçbiri veritabanından gelmez — hepsi koddan ve
 * yapılandırmadan okunur.
 */
final class ShowInvestorPageController extends Controller
{
    /**
     * Adres → [şablon, ölçüm kimliği].
     *
     * Ölçüm kimliği ADRESTEN TÜREMEZ (`docs/100` Faz 3): adres yarın
     * değişirse geçmiş raporlar ikiye bölünmemeli.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PAGES = [
        '/investors' => ['public.investors.overview', 'investors'],
        '/investors/product' => ['public.investors.product', 'investors.product'],
        '/investors/deck' => ['public.investors.deck', 'investors.deck'],
        '/investors/contact' => ['public.investors.contact', 'investors.contact'],
    ];

    public function __construct(
        private readonly SiteShell $shell,
        private readonly InvestorDossier $dossier,
        private readonly PublicPlans $plans,
        private readonly ResponseCommitment $commitment,
    ) {}

    public function __invoke(Request $request): View
    {
        $path = rtrim($request->getPathInfo(), '/');
        [$view, $pageKey] = self::PAGES[$path] ?? self::PAGES['/investors'];

        $locale = SiteText::pick($request->getPreferredLanguage(['en', 'tr']));
        $shell = $this->shell->context($request, $pageKey, $path);
        $facts = $this->dossier->facts($locale);

        return view($view, $shell + [
            'facts' => $facts,
            // Sayılar CÜMLENİN İÇİNDE: şablon yer tutucu bilmez, denetleyici
            // doldurur (`contactSentReference` ile aynı desen, `docs/125`).
            'measured' => $this->sentences($shell['st'], $facts),
            'deck' => $this->deck($shell['st'], $facts),
            'plans' => $this->plans->forLocale($locale),
            // `null` = yapılandırılmamış = sayfada hiçbir taahhüt yok.
            'commitment' => $this->commitment->sentence($locale),
        ]);
    }

    /**
     * Ölçülen sayıların yerleştirildiği cümleler.
     *
     * @param  array<string, string>  $st
     * @param  array<string, mixed>  $facts
     * @return array<string, string>
     */
    private function sentences(array $st, array $facts): array
    {
        /** @var array<string, int> $counts */
        $counts = $facts['counts'];

        return [
            'built' => $this->fill($st['investorsBuiltLead'], $counts),
            'limits' => $this->fill($st['investorsLimitsLead'], $counts),
            'verification' => $this->fill($st['investorsVerificationLead'], $counts),
            'gates' => $this->fill($st['investorsVerificationGates'], $counts),
            'subprocessors' => $this->fill($st['investorsInfrastructureLead'], $counts),
        ];
    }

    /** @param  array<string, int>  $counts */
    private function fill(string $sentence, array $counts): string
    {
        foreach ($counts as $name => $value) {
            $sentence = str_replace('{'.$name.'}', (string) $value, $sentence);
        }

        return $sentence;
    }

    /**
     * DECK — aynı olgular, bir kez okunacak SIRADA.
     *
     * Her panelin `evidence` satırı ÖLÇÜLEN bir değerden kurulur; katalogda
     * duran şey yalnız cümlenin kendisidir. Bir panelin kanıtı yoksa panel de
     * yoktur: kanıtsız bir slayt, tam olarak bu sayfanın reddettiği şeydir.
     *
     * @param  array<string, string>  $st
     * @param  array<string, mixed>  $facts
     * @return list<array{title: string, body: string, evidence: string}>
     */
    private function deck(array $st, array $facts): array
    {
        /** @var array<string, int> $counts */
        $counts = $facts['counts'];
        /** @var array{committed: bool, missing: list<string>} $commitment */
        $commitment = $facts['commitment'];

        $panels = [
            ['investorsDeckWhat', $this->fill($st['investorsDeckWhatEvidence'], $counts)],
            ['investorsDeckBuilt', $this->fill($st['investorsDeckBuiltEvidence'], $counts)],
            ['investorsDeckNotBuilt', $this->fill($st['investorsDeckNotBuiltEvidence'], $counts)],
            ['investorsDeckPrice', $st['investorsDeckPriceEvidence']],
            [
                'investorsDeckCommitment',
                $commitment['committed']
                    ? $st['investorsCommitmentSet']
                    : $st['investorsCommitmentMissingLabel'].' '.implode(', ', $commitment['missing']),
            ],
            ['investorsDeckRuns', $this->fill($st['investorsDeckRunsEvidence'], $counts)],
            ['investorsDeckChecked', $this->fill($st['investorsDeckCheckedEvidence'], $counts)],
            /*
                SON PANEL BİLİNMEYENDİR ve sayısı yoktur. Bir yatırımcı
                sayfasının en dürüst satırı, ölçülmemiş olanı ölçülmüş gibi
                göstermeyen satırdır; bu yüzden buranın kanıtı bir rakam değil,
                ölçümün YOKLUĞUNUN kendisidir.
            */
            ['investorsDeckUnknown', $st['investorsDeckUnknownEvidence']],
        ];

        return array_map(
            static fn (array $panel): array => [
                'title' => $st[$panel[0].'Heading'],
                'body' => $st[$panel[0].'Body'],
                'evidence' => $panel[1],
            ],
            $panels,
        );
    }
}
