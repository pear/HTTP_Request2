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
 * @copyright 2008-2016 Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      http://pear.php.net/package/HTTP_Request2
 */

if ('@' . 'package_version@' !== '@package_version@') {
    // Installed with PEAR: we should be on the include path and require_once should be enabled
    $installed = true;

} else {
    foreach (array(dirname(__FILE__) . '/../../../autoload.php', dirname(__FILE__) . '/../vendor/autoload.php') as $file) {
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

if (strpos($_SERVER['argv'][0], 'phpunit') === false
    && !class_exists('PHPUnit_Framework_TestCase', true)
) {
    require_once 'PHPUnit/Autoload.php';
}

if (!defined('HTTP_REQUEST2_TESTS_BASE_URL')
    && is_readable(dirname(__FILE__) . '/NetworkConfig.php')
) {
    require_once dirname(__FILE__) . '/NetworkConfig.php';
}
?>