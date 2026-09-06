<?php

declare(strict_types=1);

return [

    /*
     * Yanıt taahhüdü, SAAT — `docs/125` §3.
     *
     * BOŞ bırakılırsa hiçbir sayfa, e-posta ya da panel bir yanıt süresi
     * yazmaz. Sayı SAHİBİN kararıdır ve henüz verilmedi; "24" yazmak bir
     * yazılımcının uydurması olurdu. Verildiği gün üç yüzey aynı cümleyi
     * tek anahtardan (`site.support.commitment`) gösterir.
     */
    'response_commitment_hours' => env('SUPPORT_RESPONSE_COMMITMENT_HOURS'),

    /*
     * Destek kanalının kendi adresi — alındı e-postasının "cevapla"
     * adresi. BOŞ bırakılırsa alındı e-postasına cevap adresi konmaz ve
     * "bu e-postayı cevaplayabilirsiniz" cümlesi de yazılmaz: kimsenin
     * okumadığı bir kutuya cevap yazdırmak, hiç cevap istememekten kötü.
     *
     * Sahibe giden bildirimin adresi AYRIDIR: `contact.notify`.
     */
    'channel_email' => env('SUPPORT_EMAIL'),

];
