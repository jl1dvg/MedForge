<?php
require_once __DIR__ . '/../bootstrap.php';

use Core\Auth;

Auth::logout();

header('Location: /auth/login?logged_out=1');
exit();
