<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Http\Controllers\Rating\UpdateRatingReplyController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Rating\Concerns\BuildsRatingFixture;
use Tests\TestCase;

/**
 * SAHİBİN YANITI — `docs/116` §4 (P6).
 *
 * ═══ SİLEBİLİYORSA ORTALAMA BİR PAZARLAMA SAYISIDIR ═══
 *
 * Sahip puana YANIT VEREBİLİR, KALDIRAMAZ. Bu cümle bu paketin tamamıdır ve
 * bir nezaket kuralı değil, ürünün ölçüm iddiasının tek dayanağıdır: sahibin
 * silebildiği bir ortalama, misafire "bu restoranın seçtiği oyların
 * ortalaması" olarak gösterilir — yani bir ölçüm değil, bir reklam.
 *
 * ═══ AMA SAHİP KENDİ SÖZÜNÜ GERİ ALABİLİR ═══
 *
 * Yanıtın kendisi sahibin KENDİ metnidir ve düzeltilebilir, kaldırılabilir.
 * Ayrım keskin ve kasıtlı: misafirin ölçümü sahibin malı değildir, sahibin
 * cümlesi ise sahibinindir. Bu ayrım olmasaydı ya sahip yanlış yazdığı bir
 * cümleye sonsuza kadar mahkûm olurdu, ya da "silme yok" kuralı ilk
 * istisnasında delinirdi.
 *
 * ═══ YANIT MİSAFİRE GÖRÜNÜR ═══
 *
 * Kimsenin görmediği bir yanıt bir yanıt değildir. Sahip yazdığında cümle
 * masadaki misafirin gördüğü menüde, puanın yanında durur — ve KİMİN
 * KONUŞTUĞU yazılıdır: restoranın cümlesiyle misafirin ölçümü aynı yerde
 * dururken karıştırılamaz.
 *
 * Requirement ID'leri: RATING-REPLY-WRITE-01, RATING-REPLY-NO-DELETE-02,
 * RATING-REPLY-VISIBLE-03, RATING-REPLY-PERMISSION-04,
 * RATING-REPLY-OWN-WORDS-05.
 */
final class OwnerRatingReplyTest extends TestCase
{
    use BuildsRatingFixture;
    use RefreshDatabase;

    /** @param  array<string, mixed>  $scene */
    private function replyPath(array $scene, string $product = 'Kahve'): string
    {
        return '/api/workspaces/'.$scene['workspaceId'].'/ratings/products/'.$scene['products'][$product].'/reply';
    }

    // --- RATING-REPLY-WRITE-01 / RATING-REPLY-VISIBLE-03 ------------------

    public function test_the_owner_can_answer_a_rating_and_the_guest_reads_the_answer(): void
    {
        $scene = $this->ratingScene('yanit-yazi', ['Kahve']);

        $this->storeRatingScore($scene['workspaceId'], $scene['products']['Kahve'], 3.2, 20, 15.0, true);

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), ['body' => 'We changed the roast in March; thank you for telling us.'])
            ->assertStatus(200);

        $html = (string) $this->withHeaders(['Accept' => 'text/html'])
            ->get('/menu/'.$scene['token'])
            ->getContent();

        self::assertStringContainsString(
            'We changed the roast in March',
            $html,
            'RATING-REPLY-VISIBLE-03: kimsenin görmediği bir yanıt bir yanıt değildir.'
        );

        self::assertStringContainsString(
            'data-rating-reply',
            $html,
            'RATING-REPLY-VISIBLE-03: yanıt KİMİN konuştuğunu belli eden kendi kabında durmalı; restoranın cümlesi misafirin ölçümüyle karışamaz.'
        );
    }

    public function test_a_reply_never_touches_the_measurement_it_answers(): void
    {
        /*
            YANIT BİR DÜZELTME DEĞİLDİR.

            Sahip yanıt yazdıktan sonra ne sinyal sayısı, ne puan, ne de
            eşik kararı değişir. Değişseydi "yanıt" sessiz bir silme aracı
            olurdu: kötü puana yanıt yazan sahip, puanı da yumuşatmış olurdu.
        */
        $scene = $this->ratingScene('yanit-dokunmaz', ['Kahve']);

        $this->storeRatingScore($scene['workspaceId'], $scene['products']['Kahve'], 2.1, 30, 22.0, true);

        $before = DB::table('rating_scores')->where('subject_id', $scene['products']['Kahve'])->first();

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), ['body' => 'We are sorry and we are fixing it.'])
            ->assertStatus(200);

        $after = DB::table('rating_scores')->where('subject_id', $scene['products']['Kahve'])->first();

        self::assertEquals($before, $after, 'RATING-REPLY-NO-DELETE-02: yanıt ölçümün tek bir alanını bile değiştiremez.');
    }

    // --- RATING-REPLY-NO-DELETE-02 ----------------------------------------

    public function test_no_route_anywhere_lets_the_owner_remove_a_rating(): void
    {
        /*
            KURALIN KENDİSİ BİR YÜZEY TESTİDİR.

            "Sahip puanı silemez" cümlesi ancak silecek bir kapı YOKSA
            doğrudur. Bir gün biri iyi niyetle `DELETE .../ratings/{id}`
            eklerse bu test kırılır — ve kırılması gerekir.

            Yanıtın kendi silme kapısı BU KURALIN İSTİSNASI DEĞİL, kapsamı
            dışındadır: sahibin kendi cümlesi ölçüm değildir.
        */
        $destructive = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_contains($uri, 'rating')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (! in_array($method, ['DELETE', 'PUT', 'PATCH'], true)) {
                    continue;
                }

                if (str_ends_with($uri, '/reply')) {
                    // Sahibin kendi sözü — RATING-REPLY-OWN-WORDS-05.
                    continue;
                }

                $destructive[] = $method.' '.$uri;
            }
        }

        self::assertSame(
            [],
            $destructive,
            'RATING-REPLY-NO-DELETE-02: puanı silen ya da değiştiren bir kapı olamaz; silebiliyorsa ortalama bir pazarlama sayısıdır.'
        );
    }

    public function test_the_guest_ledger_survives_every_reply(): void
    {
        $scene = $this->ratingScene('yanit-defter', ['Kahve']);

        $this->postJson('/q/'.$scene['token'].'/ratings', [
            'menuItemId' => $scene['menuItems']['Kahve'],
            'score' => 1,
        ])->assertStatus(201);

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), ['body' => 'Thank you, we will look into it.'])
            ->assertStatus(200);

        $this->actingAs($scene['owner'])
            ->deleteJson($this->replyPath($scene))
            ->assertStatus(200);

        self::assertSame(
            1,
            DB::table('rating_signals')->count(),
            'RATING-REPLY-NO-DELETE-02: yanıtı silmek misafirin oyunu da silseydi, silme kapısı arka kapıdan geri gelmiş olurdu.'
        );
        self::assertSame(1, (int) DB::table('rating_signals')->first()->score_value);
    }

    // --- RATING-REPLY-OWN-WORDS-05 ----------------------------------------

    public function test_the_owner_can_correct_and_withdraw_their_own_sentence(): void
    {
        $scene = $this->ratingScene('yanit-kendi-sozu', ['Kahve']);

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), ['body' => 'First try.'])
            ->assertStatus(200);

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), ['body' => 'Second, better try.'])
            ->assertStatus(200);

        self::assertSame(
            1,
            DB::table('rating_replies')->count(),
            'RATING-REPLY-OWN-WORDS-05: bir ürün için restoranın TEK bir sesi vardır; iki yanıt iki ağız demekti.'
        );
        self::assertSame('Second, better try.', DB::table('rating_replies')->first()->body);

        $this->actingAs($scene['owner'])
            ->deleteJson($this->replyPath($scene))
            ->assertStatus(200);

        self::assertSame(0, DB::table('rating_replies')->count());
    }

    public function test_an_empty_or_oversized_reply_is_refused_instead_of_being_trimmed(): void
    {
        /*
            SESSİZ KIRPMA YASAK.

            Sahibin cümlesini kırpıp yayınlamak, ona yazmadığı bir cümleyi
            söyletmektir — ve o cümle misafirin gördüğü menüde durur. Uzunluk
            sınırı SUNUCUDA yaşar; PostgreSQL'de taşan bir metin isteği
            reddeder, SQLite'ta sessizce geçerdi.
        */
        $scene = $this->ratingScene('yanit-uzunluk', ['Kahve']);

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'reply_empty');

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), [
                'body' => str_repeat('a', UpdateRatingReplyController::MAX_BODY_LENGTH + 1),
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'reply_too_long');

        self::assertSame(0, DB::table('rating_replies')->count());
    }

    // --- RATING-REPLY-PERMISSION-04 ---------------------------------------

    public function test_a_role_without_the_reply_permission_cannot_speak_for_the_restaurant(): void
    {
        /*
            YANIT MARKANIN SESİDİR.

            Menüyü yayınlayamayan bir rol, misafirin gördüğü menüde restoran
            adına cümle de kuramaz. Ret 404'tür (401/403 değil): "bu ürün var
            ama sana kapalı" bilgisi bile kiracı sınırının dışına çıkmaz.
        */
        $scene = $this->ratingScene('yanit-yetki', ['Kahve'], 'editor');

        $this->actingAs($scene['owner'])
            ->putJson($this->replyPath($scene), ['body' => 'Editor should not be able to say this.'])
            ->assertStatus(404);

        self::assertSame(0, DB::table('rating_replies')->count());
    }

    /**
     * YANIT RESTORANIN SÖZÜDÜR, YAZANIN DEĞİL.
     *
     * Ekipten biri ayrıldığında cümlesi menüden düşseydi, bir personel
     * değişikliği masadaki misafirin gördüğü sayfayı sessizce değiştirirdi
     * — ve sahip bunu ancak bir misafir sorduğunda fark ederdi.
     *
     * `author_user_id` bir SAHİPLİK değil bir KAYITTIR: "kim yazmıştı?"
     * sorusunun cevabı. O cevap kaybolabilir; cümle kaybolamaz.
     */
    public function test_a_reply_outlives_the_person_who_wrote_it(): void
    {
        $scene = $this->ratingScene('yanit-ayrilan', ['Kahve']);

        // Cümleyi YAZAN kişi, restoranı KURAN kişi değil: ekipten
        // ayrılacak bir yönetici.
        $manager = User::factory()->create(['email_verified_at' => now()]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $scene['workspaceId'],
            'user_id' => $manager->id,
            'role' => 'manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->putJson($this->replyPath($scene), ['body' => 'The kitchen changed in April.'])
            ->assertStatus(200);

        self::assertSame((int) $manager->id, (int) DB::table('rating_replies')->first()->author_user_id);

        // Yönetici ekipten ayrılır: üyeliği silinir, yetkisi biter.
        DB::table('workspace_memberships')
            ->where('workspace_id', $scene['workspaceId'])
            ->where('user_id', $manager->id)
            ->delete();

        self::assertSame(
            1,
            DB::table('rating_replies')->count(),
            'RATING-REPLY-OWN-WORDS-05: yazan gider, restoranın cümlesi kalır.'
        );

        $html = (string) $this->withHeaders(['Accept' => 'text/html'])
            ->get('/menu/'.$scene['token'])
            ->getContent();

        self::assertStringContainsString(
            'The kitchen changed in April',
            $html,
            'RATING-REPLY-OWN-WORDS-05: bir personel değişikliği misafirin gördüğü sayfayı sessizce değiştiremez.'
        );

        // Ayrılan yönetici artık restoran adına konuşamaz.
        $this->actingAs($manager)
            ->putJson($this->replyPath($scene), ['body' => 'One more thing.'])
            ->assertStatus(404);
    }
}
