<?php
/**
 * Unit tests for HTTP_Request2 package
 *
 * PHP version 5
 *
 * LICENSE
 *
 * This source file is subject to BSD 3-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.github.com/pear/HTTP_Request2/trunk/docs/LICENSE
 *
 * @category  HTTP
 * @package   HTTP_Request2
 * @author    Alexey Borzov <avb@php.net>
 * @copyright 2008-2022 Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      http://pear.php.net/package/HTTP_Request2
 */

if ('@' . 'package_version@' !== '@package_version@') {
    // Installed with PEAR: we should be on the include path, just require_once everything
    require_once 'HTTP/Request2.php';
    require_once 'HTTP/Request2/CookieJar.php';
    require_once 'HTTP/Request2/MultipartBody.php';
    require_once 'HTTP/Request2/Response.php';
    require_once 'HTTP/Request2/Adapter/Mock.php';
    require_once 'HTTP/Request2/Adapter/Socket.php';
    require_once 'HTTP/Request2/Observer/UncompressingDownload.php';
    $installed = true;

} else {
    $installed = false;
    foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
        if (file_exists($file)) {
            // found composer autoloader, use it
            require_once $file;
            $installed = true;

            break;
        }
    }
}

if (!$installed) {
    fwrite(STDERR,
        'As HTTP_Request2 has required dependencies, tests should be run either' . PHP_EOL . PHP_EOL .
        ' - after installation of package with PEAR:' . PHP_EOL .
        '    pear install package.xml' . PHP_EOL . PHP_EOL .
        ' - or setting up its dependencies using Composer:' . PHP_EOL .
        '    composer install' . PHP_EOL . PHP_EOL
    );

    die(1);
}

if (!defined('HTTP_REQUEST2_TESTS_BASE_URL')) {
    if (is_readable(__DIR__ . '/NetworkConfig.php')) {
        require_once __DIR__ . '/NetworkConfig.php';
    } else {
        require_once __DIR__ . '/NetworkConfig.php.dist';
    }
}
?>