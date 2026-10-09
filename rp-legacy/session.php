<?php
// PHP 5.6 の既定は use_strict_mode=0。攻撃者が用意したセッション ID を
// そのまま使ってしまう（セッション固定）のを防ぐため有効にする
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');   // PHP 5.6 の既定は 0
session_name('rp_legacy_session');
session_start();
