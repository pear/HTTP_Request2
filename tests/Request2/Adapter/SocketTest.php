<?php
/**
 * Unit tests for HTTP_Request2 package
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2008-2014, Alexey Borzov <avb@php.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *    * The names of the authors may not be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   HTTP
 * @package    HTTP_Request2
 * @author     Alexey Borzov <avb@php.net>
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @link       http://pear.php.net/package/HTTP_Request2
 */

/** Tests for HTTP_Request2 package that require a working webserver */
require_once dirname(__FILE__) . '/CommonNetworkTest.php';

/** Socket-based adapter for HTTP_Request2 */
require_once 'HTTP/Request2/Adapter/Socket.php';

/**
 * Unit test for Socket Adapter of HTTP_Request2
 */
class HTTP_Request2_Adapter_SocketTest extends HTTP_Request2_Adapter_CommonNetworkTest
{
   /**
    * Configuration for HTTP Request object
    * @var array
    */
    protected $config = array(
        'adapter' => 'HTTP_Request2_Adapter_Socket'
    );

    public function testBug17826()
    {
        $adapter = new HTTP_Request2_Adapter_Socket();

        $request1 = new HTTP_Request2($this->baseUrl . 'redirects.php?redirects=2');
        $request1->setConfig(array('follow_redirects' => true, 'max_redirects' => 3))
                 ->setAdapter($adapter)
                 ->send();

        $request2 = new HTTP_Request2($this->baseUrl . 'redirects.php?redirects=2');
        $request2->setConfig(array('follow_redirects' => true, 'max_redirects' => 3))
                 ->setAdapter($adapter)
                 ->send();
    }


    /**
     * Infinite loop with stream wrapper passed as upload
     *
     * Dunno how the original reporter managed to pass a file pointer
     * that doesn't support fstat() to MultipartBody, maybe he didn't use
     * addUpload(). So we don't use it, either.
     *
     * @link http://pear.php.net/bugs/bug.php?id=19934
     */
    public function testBug19934()
    {
        if (!in_array('http', stream_get_wrappers())) {
            $this->markTestSkipped("This test requires an HTTP fopen wrapper enabled");
        }

        $fp   = fopen($this->baseUrl . '/bug19934.php', 'rb');
        $body = new HTTP_Request2_MultipartBody(
            array(),
            array(
                'upload' => array(
                    'fp'       => $fp,
                    'filename' => 'foo.txt',
                    'type'     => 'text/plain',
                    'size'     => 20000
                )
            )
        );
        $this->request->setMethod(HTTP_Request2::METHOD_POST)
                      ->setUrl($this->baseUrl . 'uploads.php')
                      ->setBody($body);

        set_error_handler(array($this, 'rewindWarningsHandler'));
        $response = $this->request->send();
        restore_error_handler();

        $this->assertContains("upload foo.txt text/plain 20000", $response->getBody());
    }

    public function rewindWarningsHandler($errno, $errstr)
    {
        if (($errno & E_WARNING) && false !== strpos($errstr, 'rewind')) {
            return true;
        }
        return false;
    }

    /**
     * Do not send request body twice to URLs protected by digest auth
     *
     * @link http://pear.php.net/bugs/bug.php?id=19233
     */
    public function test100ContinueHandling()
    {
        if (!defined('HTTP_REQUEST2_TESTS_DIGEST_URL') || !HTTP_REQUEST2_TESTS_DIGEST_URL) {
            $this->markTestSkipped('This test requires an URL protected by server digest auth');
        }

        $fp   = fopen(dirname(dirname(dirname(__FILE__))) . '/_files/bug_15305', 'rb');
        $body = $this->getMock(
            'HTTP_Request2_MultipartBody', array('read'), array(
                array(),
                array(
                    'upload' => array(
                        'fp'       => $fp,
                        'filename' => 'bug_15305',
                        'type'     => 'application/octet-stream',
                        'size'     => 16338
                    )
                )
            )
        );
        $body->expects($this->never())->method('read');

        $this->request->setMethod(HTTP_Request2::METHOD_POST)
                      ->setUrl(HTTP_REQUEST2_TESTS_DIGEST_URL)
                      ->setBody($body);

        $this->assertEquals(401, $this->request->send()->getStatus());
    }

    public function test100ContinueTimeoutBug()
    {
        $fp       = fopen(dirname(dirname(dirname(__FILE__))) . '/_files/bug_15305', 'rb');
        $body     = new HTTP_Request2_MultipartBody(
            array(),
            array(
                'upload' => array(
                    'fp'       => $fp,
                    'filename' => 'bug_15305',
                    'type'     => 'application/octet-stream',
                    'size'     => 16338
                )
            )
        );

        $this->request->setMethod(HTTP_Request2::METHOD_POST)
                      ->setUrl($this->baseUrl . 'uploads.php?slowpoke')
                      ->setBody($body);

        $response = $this->request->send();
        $this->assertContains('upload bug_15305 application/octet-stream 16338', $response->getBody());
    }
}
?>