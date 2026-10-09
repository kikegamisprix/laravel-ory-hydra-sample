<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** デモユーザーを 1 人作る。パスワードは環境変数から受け取り、コードには置かない */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('DEMO_USER_EMAIL', 'demo@example.com');
        $password = env('DEMO_USER_PASSWORD');
        if (! $password) {
            $this->command->warn('DEMO_USER_PASSWORD が未設定のためデモユーザーを作成しません');
            return;
        }
        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Demo User', 'password' => $password]
        );
    }
}
