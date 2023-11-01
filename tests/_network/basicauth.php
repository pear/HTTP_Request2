<?php
/**
 * Helper files for HTTP_Request2 unit tests. Should be accessible via HTTP.
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
 * @copyright 2008-2023 Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      http://pear.php.net/package/HTTP_Request2
 */

$user       = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
$pass       = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
$wantedUser = isset($_GET['user']) ? $_GET['user'] : null;
$wantedPass = isset($_GET['pass']) ? $_GET['pass'] : null;

if (!$user || !$pass || $user != $wantedUser || $pass != $wantedPass) {
    header('WWW-Authenticate: Basic realm="HTTP_Request2 tests"', true, 401);
    echo "Login required";
} else {
    echo "Username={$user};Password={$pass}";
}

?>