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
 * @copyright 2008-2023 Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      http://pear.php.net/package/HTTP_Request2
 */

/**
 * Makes a 3 second delay before sending the request body
 */
class HTTP_Request2_Adapter_SlowpokeBody extends HTTP_Request2_MultipartBody
{
    protected $doSleep;

    public function rewind()
    {
        $this->doSleep = true;
        parent::rewind();
    }

    public function read($length)
    {
        if ($this->doSleep) {
            sleep(3);
            $this->doSleep = false;
        }
        return parent::read($length);
    }
}
?>
