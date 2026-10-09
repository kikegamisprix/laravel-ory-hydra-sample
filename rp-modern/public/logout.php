<?php
require __DIR__ . '/../oidc.php';
startSession();
session_destroy();
header('Location: /');
