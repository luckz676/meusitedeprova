<?php
/**
 * Logout
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

fazerLogout();
header('Location: /avaliacao web/login.php');
exit;