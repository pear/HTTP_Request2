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
 * @copyright 2008-2026 Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      https://pear.php.net/package/HTTP_Request2
 */

$redirects = isset($_GET['redirects'])? $_GET['redirects']: 1;
$https     = !empty($_SERVER['HTTPS']) && ('off' != strtolower($_SERVER['HTTPS']));
$special   = isset($_GET['special'])? $_GET['special']: null;

if ('ftp' == $special) {
    header('Location: ftp://localhost/pub/exploit.exe', true, 301);

} elseif ('youtube' == $special) {
    header('Location: https://youtube.com/', true, 301);

} elseif ('relative' == $special) {
    header('Location: ./getparameters.php?msg=did%20relative%20redirect', true, 302);

} elseif ('cookie' == $special) {
    setcookie('cookie_on_redirect', 'success');
    header('Location: ./cookies.php', true, 302);

} elseif ($redirects > 0) {
    $url = ($https? 'https': 'http') . '://' . $_SERVER['SERVER_NAME']
           . (($https && 443 == $_SERVER['SERVER_PORT'] || !$https && 80 == $_SERVER['SERVER_PORT'])
              ? '' : ':' . $_SERVER['SERVER_PORT'])
           . $_SERVER['PHP_SELF'] . '?redirects=' . (--$redirects);
    header('Location: ' . $url, true, 302);

} else {
    echo "Method=" . htmlspecialchars($_SERVER['REQUEST_METHOD'], ENT_NOQUOTES, 'UTF-8') . ';';
    echo htmlspecialchars(serialize($_POST), ENT_NOQUOTES, 'UTF-8');
    echo htmlspecialchars(serialize($_GET), ENT_NOQUOTES, 'UTF-8');
}
?>