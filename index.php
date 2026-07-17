<?php
require_once __DIR__ . '/auth.php';
redirect(isLoggedIn() ? 'dashboard.php' : 'login.php');
