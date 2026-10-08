<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

logout_user();
start_app_session();
flash_set('success', 'You have been logged out.');
redirect('login.php');
