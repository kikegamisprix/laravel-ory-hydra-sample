<?php

return [
    'admin_url' => env('HYDRA_ADMIN_URL', 'http://hydra:4445'),
    // 「記憶」の期間（秒）。パスワード入力時の Hydra ログインセッションと、同意の記憶の両方に使う
    'remember_for' => 3600,
    // Laravel セッションの再利用でパスワード入力なしに受理してよい、最後のパスワード入力からの上限（秒）。
    // Laravel のセッションは無操作タイムアウトで延び続けるので、絶対上限を別に持つ
    'max_sso_age' => 8 * 3600,
];
