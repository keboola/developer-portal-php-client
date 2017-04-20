<?php
define('ROOT_PATH', __DIR__);
ini_set('display_errors', true);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Prague');

set_error_handler('exceptions_error_handler');
function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}

defined('KBDP_API_URL')
|| define('KBDP_API_URL', getenv('KBDP_API_URL') ? getenv('KBDP_API_URL') : '');

defined('KBDP_USERNAME')
|| define('KBDP_USERNAME', getenv('KBDP_USERNAME') ? getenv('KBDP_USERNAME') : '');

defined('KBDP_PASSWORD')
|| define('KBDP_PASSWORD', getenv('KBDP_PASSWORD') ? getenv('KBDP_PASSWORD') : '');

defined('KBDP_VENDOR')
|| define('KBDP_VENDOR', getenv('KBDP_VENDOR') ? getenv('KBDP_VENDOR') : '');

require_once __DIR__ . '/../vendor/autoload.php';
