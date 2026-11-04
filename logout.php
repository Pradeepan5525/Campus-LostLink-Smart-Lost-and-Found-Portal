<?php
/**
 * Campus LostLink — Logout Script
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout_user();

start_session_safe();
set_flash('info', 'You have been logged out successfully.');
header('Location: ' . BASE_URL . '/login.php');
exit;
