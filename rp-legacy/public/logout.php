<?php
session_name('rp_legacy_session');
session_start();
session_destroy();
header('Location: /');
