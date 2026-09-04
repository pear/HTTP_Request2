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

ksort($_COOKIE);
echo htmlspecialchars(serialize($_COOKIE), ENT_NOQUOTES, 'UTF-8');

?>