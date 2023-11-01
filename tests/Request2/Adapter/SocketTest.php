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

/** Tests for HTTP_Request2 package that require a working webserver */
require_once __DIR__ . '/CommonNetworkTest.php';

/**
 * Unit test for Socket Adapter of HTTP_Request2
 */
class HTTP_Request2_Adapter_SocketTest extends HTTP_Request2_Adapter_CommonNetworkTest
{
   /**
    * Configuration for HTTP Request object
    * @var array
    */
    protected $config = [
        'adapter' => \HTTP_Request2_Adapter_Socket::class
    ];

    /**
     * @doesNotPerformAssertions
     */
    public function testBug17826()
    {
        $adapter = new HTTP_Request2_Adapter_Socket();

        $request1 = new HTTP_Request2($this->baseUrl . 'redirects.php?redirects=2');
        $request1->setConfig(['follow_redirects' => true, 'max_redirects' => 3])
                 ->setAdapter($adapter)
                 ->send();

        $request2 = new HTTP_Request2($this->baseUrl . 'redirects.php?redirects=2');
        $request2->setConfig(['follow_redirects' => true, 'max_redirects' => 3])
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
            [],
            [
                'upload' => [
                    'fp'       => $fp,
                    'filename' => 'foo.txt',
                    'type'     => 'text/plain',
                    'size'     => 20000
                ]
            ]
        );
        $this->request->setMethod(HTTP_Request2::METHOD_POST)
                      ->setUrl($this->baseUrl . 'uploads.php')
                      ->setBody($body);

        set_error_handler([$this, 'rewindWarningsHandler']);
        $response = $this->request->send();
        restore_error_handler();

        $this->assertStringContainsString("upload foo.txt text/plain 20000", $response->getBody());
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
        if (!HTTP_REQUEST2_TESTS_DIGEST_URL) {
            $this->markTestSkipped('This test requires an URL protected by server digest auth');
        }

        $fp   = fopen(dirname(dirname(__DIR__)) . '/_files/bug_15305', 'rb');
        $body = $this->getMockBuilder(\HTTP_Request2_MultipartBody::class)
            ->setMethods(['read'])
            ->setConstructorArgs([
                [],
                [
                    'upload' => [
                        'fp'       => $fp,
                        'filename' => 'bug_15305',
                        'type'     => 'application/octet-stream',
                        'size'     => 16338
                    ]
                ]
            ])
            ->getMock();
        $body->expects($this->never())->method('read');

        $this->request->setMethod(HTTP_Request2::METHOD_POST)
                      ->setUrl(HTTP_REQUEST2_TESTS_DIGEST_URL)
                      ->setBody($body);

        $this->assertEquals(401, $this->request->send()->getStatus());
    }

    public function test100ContinueTimeoutBug()
    {
        $fp       = fopen(dirname(dirname(__DIR__)) . '/_files/bug_15305', 'rb');
        $body     = new HTTP_Request2_MultipartBody(
            [],
            [
                'upload' => [
                    'fp'       => $fp,
                    'filename' => 'bug_15305',
                    'type'     => 'application/octet-stream',
                    'size'     => 16338
                ]
            ]
        );

        $this->request->setMethod(HTTP_Request2::METHOD_POST)
                      ->setUrl($this->baseUrl . 'uploads.php?slowpoke')
                      ->setBody($body);

        $response = $this->request->send();
        $this->assertStringContainsString('upload bug_15305 application/octet-stream 16338', $response->getBody());
    }

    /**
     * Socket adapter should not throw an exception (invalid chunk length '')
     * if a buggy server doesn't send last zero-length chunk when using chunked encoding
     *
     * @link http://pear.php.net/bugs/bug.php?id=20228
     */
    public function testBug20228()
    {
        $events = ['receivedBodyPart', 'warning', 'receivedBody'];
        $this->request->setHeader('Accept-Encoding', 'identity')
            ->attach($observer = new HTTP_Request2_Adapter_EventSequenceObserver($events));
        $response = $this->request->send();

        if (false === strpos((string)$response->getHeader('Server'), 'Apache')) {
            $this->markTestSkipped("This test will likely fail on non-Apache web server");
        }

        $this->assertEquals('This is a test', $response->getBody());
        $this->assertEquals($events, $observer->sequence);
    }

    public function testHowsMySSL()
    {
        if (!in_array('ssl', stream_get_transports())) {
            $this->markTestSkipped("This test requires SSL support");
        }

        $this->request->setUrl('https://www.howsmyssl.com/a/check')
            ->setConfig('ssl_verify_peer', false);

        if (null === ($responseData = json_decode($this->request->send()->getBody(), true))) {
            $this->fail('Cannot decode JSON from howsmyssl.com response');
        }

        $this->assertEmpty($responseData['insecure_cipher_suites']);

        $this->assertEquals('Probably Okay', $responseData['rating']);
    }

    public function testDefaultSocketTimeout()
    {
        ini_set('default_socket_timeout', 2);

        $this::expectException(\HTTP_Request2_MessageException::class);
        $this::expectExceptionMessageMatches('/default_socket_timeout/');
        try {
            $this->request->setConfig('timeout', 0)
                ->setUrl($this->baseUrl . 'timeout.php')
                ->send();
        } finally {
            ini_restore('default_socket_timeout');
        }
    }
}
?>