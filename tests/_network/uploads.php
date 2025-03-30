<?php
/**
 * WARNING: This file is a part of test suite for PEAR/HTTP_Request2. It should NOT be served on public websites.
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
 * @copyright 2008-2025 Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      https://pear.php.net/package/HTTP_Request2
 */

if (isset($_GET['slowpoke'])) {
    sleep(3);
}

foreach ($_FILES as $name => $file) {
    if (is_array($file['name'])) {
        foreach($file['name'] as $k => $v) {
            echo htmlspecialchars("{$name}[{$k}] {$v} {$file['type'][$k]} {$file['size'][$k]}\n", ENT_NOQUOTES, 'UTF-8');
        }
    } else {
        echo htmlspecialchars("{$name} {$file['name']} {$file['type']} {$file['size']}\n", ENT_NOQUOTES, 'UTF-8');
    }
}
?>