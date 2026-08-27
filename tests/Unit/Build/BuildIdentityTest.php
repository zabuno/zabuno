<?php

declare(strict_types=1);

namespace Tests\Unit\Build;

use App\Support\Build\BuildIdentity;
use App\Support\Build\GitHead;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Preview Truth'un sunucu yarısı (docs/52).
 *
 * Buradaki testlerin çoğu `GitHead` üzerinedir ve bu tesadüf değil: dedektörün
 * TEK zayıf noktası, sürümü hiç çözememesidir. Sürüm `null` olduğunda
 * karşılaştırma yapılmaz ve kapı sessizce her şeye "temiz" der — yani arıza
 * biçimi "yanlış alarm" değil, "hiç alarm vermemek"tir. Bu yüzden asıl
 * sınanan şey, git'in gerçekte kullandığı ÜÇ yerleşimin de okunabilmesidir.
 */
final class BuildIdentityTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/zabuno-build-'.bin2hex(random_bytes(6));
        mkdir($this->tmp.'/.git', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    #[Test]
    public function it_reads_a_detached_head_written_directly_as_a_sha(): void
    {
        $sha = str_repeat('a1b2c3d4', 5);
        file_put_contents($this->tmp.'/.git/HEAD', $sha."\n");

        $this->assertSame($sha, GitHead::read($this->tmp));
    }

    #[Test]
    public function it_follows_a_branch_reference_to_its_loose_ref_file(): void
    {
        $sha = str_repeat('0f1e2d3c', 5);
        file_put_contents($this->tmp.'/.git/HEAD', "ref: refs/heads/main\n");
        mkdir($this->tmp.'/.git/refs/heads', 0777, true);
        file_put_contents($this->tmp.'/.git/refs/heads/main', $sha."\n");

        $this->assertSame($sha, GitHead::read($this->tmp));
    }

    /**
     * `git gc` referansları tek tek dosyalardan `packed-refs` içine taşır.
     * Yalnız gevşek dosyalara bakan bir okuyucu, deponun bakım görmesiyle
     * SESSİZCE çalışmayı bırakır — ve sessizce çalışmayı bırakan bir dedektör,
     * hiç olmayan bir dedektörden daha tehlikelidir: varlığına güvenilir.
     */
    #[Test]
    public function it_resolves_a_branch_that_git_gc_has_packed_away(): void
    {
        $sha = str_repeat('9a8b7c6d', 5);
        file_put_contents($this->tmp.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents(
            $this->tmp.'/.git/packed-refs',
            "# pack-refs with: peeled fully-peeled sorted \n".$sha." refs/heads/main\n",
        );

        $this->assertSame($sha, GitHead::read($this->tmp));
    }

    /**
     * Worktree'de `.git` bir DİZİN DEĞİL, DOSYADIR.
     *
     * Yanlış sürümü sunan localhost çalışma zamanı tam olarak budur. Bu dal
     * desteklenmezse dedektör, kurulmuş olduğu tek hedefi ıskalar.
     */
    #[Test]
    public function it_follows_a_worktree_gitdir_pointer_file(): void
    {
        $sha = str_repeat('deadbee5', 5);
        $repo = $this->tmp.'/repo';
        $worktree = $this->tmp.'/wt';
        mkdir($repo.'/.git/worktrees/wt', 0777, true);
        mkdir($worktree, 0777, true);

        file_put_contents($worktree.'/.git', 'gitdir: '.$repo.'/.git/worktrees/wt'."\n");
        file_put_contents($repo.'/.git/worktrees/wt/HEAD', $sha."\n");

        $this->assertSame($sha, GitHead::read($worktree));
    }

    /**
     * Worktree'de referanslar ORTAK dizinde durur; `commondir` oraya götürür.
     */
    #[Test]
    public function it_resolves_a_worktree_branch_through_the_common_directory(): void
    {
        $sha = str_repeat('c0ffee12', 5);
        $repo = $this->tmp.'/repo';
        $worktree = $this->tmp.'/wt';
        mkdir($repo.'/.git/worktrees/wt', 0777, true);
        mkdir($repo.'/.git/refs/heads', 0777, true);
        mkdir($worktree, 0777, true);

        file_put_contents($worktree.'/.git', 'gitdir: '.$repo.'/.git/worktrees/wt'."\n");
        file_put_contents($repo.'/.git/worktrees/wt/HEAD', "ref: refs/heads/feature\n");
        file_put_contents($repo.'/.git/worktrees/wt/commondir', "../..\n");
        file_put_contents($repo.'/.git/refs/heads/feature', $sha."\n");

        $this->assertSame($sha, GitHead::read($worktree));
    }

    /**
     * Çözemediğinde `null` döner — uydurmaz.
     *
     * Uydurulmuş bir sürüm, karşılaştırmayı her zaman "eşit" yapar ve dedektörü
     * sessizce işlevsiz kılar. Bilmemek, yanlış bilmekten iyidir.
     */
    #[Test]
    public function it_returns_null_rather_than_guessing_when_git_cannot_be_read(): void
    {
        $this->assertNull(GitHead::read($this->tmp.'/nothing-here'));

        file_put_contents($this->tmp.'/.git/HEAD', "ref: refs/heads/missing\n");
        $this->assertNull(GitHead::read($this->tmp));
    }

    #[Test]
    public function it_reports_a_build_as_stale_when_source_changed_after_it(): void
    {
        $fresh = BuildIdentity::fromValues('abc', builtAt: 2_000, sourceChangedAt: 1_000);
        $stale = BuildIdentity::fromValues('abc', builtAt: 1_000, sourceChangedAt: 2_000);

        $this->assertFalse($fresh->isBuildStale());
        $this->assertTrue($stale->isBuildStale());
    }

    /**
     * Bilinmeyen taraf varken bayatlık İDDİA EDİLMEZ. Aksi hâlde derlenmemiş
     * her ortam kalıcı bir uyarı gösterirdi; sürekli görünen bir uyarı ise
     * kapatılan bir uyarıdır ve o andan sonra gerçek ayrışmayı da göstermez.
     */
    #[Test]
    public function it_makes_no_staleness_claim_when_either_timestamp_is_unknown(): void
    {
        $this->assertFalse(BuildIdentity::fromValues('abc', null, 2_000)->isBuildStale());
        $this->assertFalse(BuildIdentity::fromValues('abc', 2_000, null)->isBuildStale());
    }

    #[Test]
    public function it_shortens_a_revision_to_the_length_git_itself_prints(): void
    {
        $identity = BuildIdentity::fromValues(str_repeat('ab', 20), null, null);

        $this->assertSame('abababa', $identity->shortRevision());
        $this->assertNull(BuildIdentity::fromValues(null, null, null)->shortRevision());
    }
}
