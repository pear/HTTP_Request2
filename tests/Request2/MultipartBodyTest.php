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
 * Unit test for HTTP_Request2_MultipartBody class
 */
class HTTP_Request2_MultipartBodyTest extends TestCase
{
    public function testUploadSimple()
    {
        $req = new HTTP_Request2(null, HTTP_Request2::METHOD_POST);
        $body = $req->addPostParameter('foo', 'I am a parameter')
                    ->addUpload('upload', dirname(__DIR__) . '/_files/plaintext.txt')
                    ->getBody();

        $this->assertTrue($body instanceof HTTP_Request2_MultipartBody);
        $asString = $body->__toString();
        $boundary = $body->getBoundary();
        $this->assertEquals($body->getLength(), strlen($asString));
        $this->assertStringContainsString('This is a test.', $asString);
        $this->assertStringContainsString('I am a parameter', $asString);
        $this->assertMatchesRegularExpression("!--{$boundary}--\r\n$!", $asString);
    }

   public function testRequest16863()
    {
        $this->expectException(\HTTP_Request2_LogicException::class);
        $req  = new HTTP_Request2(null, HTTP_Request2::METHOD_POST);
        $fp   = fopen(dirname(__DIR__) . '/_files/plaintext.txt', 'rb');
        $body = $req->addUpload('upload', $fp)
                    ->getBody();

        $asString = $body->__toString();
        $this->assertStringContainsString('name="upload"; filename="anonymous.blob"', $asString);
        $this->assertStringContainsString('This is a test.', $asString);

        $req->addUpload('bad_upload', fopen('php://input', 'rb'));
    }

    public function testStreaming()
    {
        $req = new HTTP_Request2(null, HTTP_Request2::METHOD_POST);
        $body = $req->addPostParameter('foo', 'I am a parameter')
                    ->addUpload('upload', dirname(__DIR__) . '/_files/plaintext.txt')
                    ->getBody();
        $asString = '';
        while ($part = $body->read(10)) {
            $asString .= $part;
        }
        $this->assertEquals($body->getLength(), strlen($asString));
        $this->assertStringContainsString('This is a test.', $asString);
        $this->assertStringContainsString('I am a parameter', $asString);
    }

    public function testUploadArray()
    {
        $req = new HTTP_Request2(null, HTTP_Request2::METHOD_POST);
        $body = $req->addUpload('upload', [
                                    [dirname(__DIR__) . '/_files/plaintext.txt', 'bio.txt', 'text/plain'],
                                    [fopen(dirname(__DIR__) . '/_files/empty.gif', 'rb'), 'photo.gif', 'image/gif']
        ])
                    ->getBody();
        $asString = $body->__toString();
        $this->assertStringContainsString(file_get_contents(dirname(__DIR__) . '/_files/empty.gif'), $asString);
        $this->assertStringContainsString('name="upload[0]"; filename="bio.txt"', $asString);
        $this->assertStringContainsString('name="upload[1]"; filename="photo.gif"', $asString);

        $body2 = $req->setConfig(['use_brackets' => false])->getBody();
        $asString = $body2->__toString();
        $this->assertStringContainsString('name="upload"; filename="bio.txt"', $asString);
        $this->assertStringContainsString('name="upload"; filename="photo.gif"', $asString);
    }
}
?>