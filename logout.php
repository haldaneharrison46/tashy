<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
logout_user();
redirect(SITE_URL . '/index.php');
