<?php
require __DIR__ . '/../session.php';
session_destroy();
header('Location: /');
