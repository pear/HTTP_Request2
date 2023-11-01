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

/** Sets up includes */
require_once dirname(__DIR__) . '/TestHelper.php';

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Unit test for HTTP_Request2_Response class
 */
class HTTP_Request2_ResponseTest extends TestCase
{
    public function testParseStatusLine()
    {
        $this->expectException(\HTTP_Request2_MessageException::class);
        $response = new HTTP_Request2_Response('HTTP/1.1 200 OK');
        $this->assertEquals('1.1', $response->getVersion());
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('OK', $response->getReasonPhrase());

        $response2 = new HTTP_Request2_Response('HTTP/1.2 222 Nishtyak!');
        $this->assertEquals('1.2', $response2->getVersion());
        $this->assertEquals(222, $response2->getStatus());
        $this->assertEquals('Nishtyak!', $response2->getReasonPhrase());

        $response3 = new HTTP_Request2_Response('HTTP/1.1 200 ');
        $this->assertEquals('1.1', $response3->getVersion());
        $this->assertEquals(200, $response3->getStatus());
        $this->assertEquals('OK', $response3->getReasonPhrase());

        $response4 = new HTTP_Request2_Response("HTTP/1.1 200 \r\n");
        $this->assertEquals('1.1', $response4->getVersion());
        $this->assertEquals(200, $response4->getStatus());
        $this->assertEquals('OK', $response4->getReasonPhrase());

        // RFC 9112 gives the following definition for reason-phrase:
        // > reason-phrase  = 1*( HTAB / SP / VCHAR / obs-text )
        // so do not use trim() and consider whitespace-only reason-phrase as present
        $response5 = new HTTP_Request2_Response("HTTP/1.1 200  \r\n");
        $this->assertEquals('1.1', $response5->getVersion());
        $this->assertEquals(200, $response5->getStatus());
        $this->assertEquals(' ', $response5->getReasonPhrase());

        new HTTP_Request2_Response('Invalid status line');
    }

    /**
     * https://www.rfc-editor.org/rfc/rfc9112.html#name-status-line
     *
     * > A server MUST send the space that separates the status-code from
     * > the reason-phrase even when the reason-phrase is absent
     * > (i.e., the status-line would end with the space).
     */
    public function testSpaceIsRequiredAfterStatusCode()
    {
        $this->expectException(\HTTP_Request2_MessageException::class);
        new HTTP_Request2_Response('HTTP/1.1 200');
    }

    public function testParseHeaders()
    {
        $response = $this->readResponseFromFile('response_headers');
        $this->assertEquals(7, count($response->getHeader()));
        $this->assertEquals('PHP/6.2.2', $response->getHeader('X-POWERED-BY'));
        $this->assertEquals('text/html; charset=windows-1251', $response->getHeader('cOnTeNt-TyPe'));
        $this->assertEquals('accept-charset, user-agent', $response->getHeader('vary'));
    }

    public function testParseCookies()
    {
        $response = $this->readResponseFromFile('response_cookies');
        $cookies  = $response->getCookies();
        $this->assertEquals(4, count($cookies));
        $expected = [
            ['name' => 'foo', 'value' => 'bar', 'expires' => null,
                  'domain' => null, 'path' => null, 'secure' => false],
            ['name' => 'PHPSESSID', 'value' => '1234567890abcdef1234567890abcdef',
                  'expires' => null, 'domain' => null, 'path' => '/', 'secure' => true],
            ['name' => 'A', 'value' => 'B=C', 'expires' => null,
                  'domain' => null, 'path' => null, 'secure' => false],
            ['name' => 'baz', 'value' => '%20a%20value', 'expires' => 'Sun, 03 Jan 2010 03:04:05 GMT',
                  'domain' => 'pear.php.net', 'path' => null, 'secure' => false],
        ];
        foreach ($cookies as $k => $cookie) {
            $this->assertEquals($expected[$k], $cookie);
        }
    }

    public function testGzipEncoding()
    {
        $this->expectException(\HTTP_Request2_MessageException::class);
        $response = $this->readResponseFromFile('response_gzip');
        $this->assertEquals('0e964e9273c606c46afbd311b5ad4d77', md5($response->getBody()));

        $response = $this->readResponseFromFile('response_gzip_broken');
        $response->getBody();
    }

    public function testDeflateEncoding()
    {
        $response = $this->readResponseFromFile('response_deflate');
        $this->assertEquals('0e964e9273c606c46afbd311b5ad4d77', md5($response->getBody()));
    }

    public function testBug15305()
    {
        $response = $this->readResponseFromFile('bug_15305');
        $this->assertEquals('c8c5088fc8a7652afef380f086c010a6', md5($response->getBody()));
    }

    public function testBug18169()
    {
        $response = $this->readResponseFromFile('bug_18169');
        $this->assertEquals('', $response->getBody());
    }

    protected function readResponseFromFile($filename)
    {
        $fp       = fopen(dirname(__DIR__) . '/_files/' . $filename, 'rb');
        $response = new HTTP_Request2_Response(fgets($fp));
        do {
            $headerLine = fgets($fp);
            $response->parseHeaderLine($headerLine);
        } while ('' != trim($headerLine));

        while (!feof($fp)) {
            $response->appendBody(fread($fp, 1024));
        }
        return $response;
    }
}
?>