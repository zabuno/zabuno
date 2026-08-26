<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Yerel QA hesabı, kimlik bilgileri verilmişse geri kurulur. Bu sayede
        // `migrate:fresh --seed` hesabı elle müdahale olmadan geri getirir —
        // hesabın "yine kayboldu" döngüsünü kıran adım budur. CI'da ve
        // kimlik bilgisi verilmeyen her ortamda sessizce atlanır.
        if (LocalTestAccountSeeder::isConfigured()) {
            $this->call(LocalTestAccountSeeder::class);
        }
    }
}
