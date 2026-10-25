<?php
// Do not hide third-party or SDK deprecations during compatibility verification.
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) || in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
require getenv('SDK_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
