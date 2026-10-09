<?php

return [
    'admin_url' => env('HYDRA_ADMIN_URL', 'http://hydra:4445'),
    // 「ログイン状態を記憶」の期間（秒）。Hydra 側のログインセッションに適用される
    'remember_for' => 3600,
];
