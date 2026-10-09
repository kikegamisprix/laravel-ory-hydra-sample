<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\HydraAdmin;
use Illuminate\Console\Command;

/**
 * ユーザーを無効化する。台帳を更新するだけでなく、Hydra 側のログインセッションと
 * 発行済みトークンも失効させる。退職時の「即ログイン不可」はこの 3 点セットで成立する
 */
class DisableUser extends Command
{
    protected $signature = 'user:disable {email} {--enable : 無効化を解除する}';
    protected $description = 'ユーザーを無効化し、Hydra のセッションとトークンを失効させる';

    public function handle(HydraAdmin $hydra): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('ユーザーが見つかりません');
            return self::FAILURE;
        }

        if ($this->option('enable')) {
            $user->forceFill(['disabled_at' => null])->save();
            $this->info("有効化しました: {$user->email}");
            return self::SUCCESS;
        }

        $user->forceFill(['disabled_at' => now()])->save();
        $hydra->revokeLoginSessions((string) $user->id);
        $hydra->revokeConsentSessions((string) $user->id);
        $this->info("無効化しました: {$user->email}（Hydra のセッションとトークンも失効）");
        return self::SUCCESS;
    }
}
