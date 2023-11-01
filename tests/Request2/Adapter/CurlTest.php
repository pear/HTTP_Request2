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

// pear-package-only require_once __DIR__ . '/UploadSizeObserver.php';

/**
 * Unit test for Curl Adapter of HTTP_Request2
 */
class HTTP_Request2_Adapter_CurlTest extends HTTP_Request2_Adapter_CommonNetworkTest
{
   /**
    * Configuration for HTTP Request object
    * @var array
    */
    protected $config = [
        'adapter' => \HTTP_Request2_Adapter_Curl::class
    ];

    protected function set_up()
    {
        parent::set_up();
        if (!extension_loaded('curl')) {
            $this->markTestSkipped("Curl extension should be enabled to run Curl tests");
        }
    }

    /**
    * Checks whether redirect support in cURL is disabled by safe_mode or open_basedir
    * @return bool
    */
    protected function isRedirectSupportDisabled()
    {
        return ini_get('safe_mode') || ini_get('open_basedir');
    }

    public function testRedirectsDefault()
    {
        if ($this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Redirect support in cURL is disabled by safe_mode or open_basedir setting');
        } else {
            parent::testRedirectsDefault();
        }
    }

    public function testRedirectsStrict()
    {
        if ($this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Redirect support in cURL is disabled by safe_mode or open_basedir setting');
        } else {
            parent::testRedirectsStrict();
        }
    }

    public function testRedirectsLimit()
    {
        if ($this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Redirect support in cURL is disabled by safe_mode or open_basedir setting');
        } else {
            parent::testRedirectsLimit();
        }
    }

    public function testRedirectsRelative()
    {
        if ($this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Redirect support in cURL is disabled by safe_mode or open_basedir setting');
        } else {
            parent::testRedirectsRelative();
        }
    }

    public function testRedirectsNonHTTP()
    {
        if ($this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Redirect support in cURL is disabled by safe_mode or open_basedir setting');
        } else {
            parent::testRedirectsNonHTTP();
        }
    }

    public function testCookieJarAndRedirect()
    {
        if ($this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Redirect support in cURL is disabled by safe_mode or open_basedir setting');
        } else {
            parent::testCookieJarAndRedirect();
        }
    }

    public function testBug17450()
    {
        if (!$this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Neither safe_mode nor open_basedir is enabled');
        }

        $this->request->setUrl($this->baseUrl . 'redirects.php')
                      ->setConfig(['follow_redirects' => true]);

        try {
            $this->request->send();
            $this->fail('Expected HTTP_Request2_Exception was not thrown');

        } catch (HTTP_Request2_LogicException $e) {
            $this->assertEquals(HTTP_Request2_Exception::MISCONFIGURATION, $e->getCode());
        }
    }

    public function testBug20440()
    {
        $this->request->setUrl($this->baseUrl . 'rawpostdata.php')
            ->setMethod(HTTP_Request2::METHOD_PUT)
            ->setHeader('Expect', '')
            ->setBody('This is a test');

        $noredirects = clone $this->request;
        $noredirects->setConfig('follow_redirects', false)
            ->attach($observer = new HTTP_Request2_Adapter_UploadSizeObserver());
        $noredirects->send();
        // Curl sends body with Transfer-encoding: chunked, so size can be larger
        $this->assertGreaterThanOrEqual(14, $observer->size);

        $redirects = clone $this->request;
        $redirects->setConfig('follow_redirects', true)
            ->attach($observer = new HTTP_Request2_Adapter_UploadSizeObserver());
        $redirects->send();
        $this->assertGreaterThanOrEqual(14, $observer->size);
    }

    /**
     * An URL performing a redirect was used for storing cookies in a jar rather than target URL
     *
     * @link http://pear.php.net/bugs/bug.php?id=20561
     */
    public function testBug20561()
    {
        if ($this->isRedirectSupportDisabled()) {
            $this->markTestSkipped('Redirect support in cURL is disabled by safe_mode or open_basedir setting');

        } else {
            $this->request->setUrl($this->baseUrl . 'redirects.php?special=youtube')
                          ->setConfig([
                                'follow_redirects' => true,
                                'ssl_verify_peer'  => false
                          ])
                          ->setCookieJar(true);

            $this->request->send();
            $this->assertGreaterThan(0, count($this->request->getCookieJar()->getAll()));
        }
    }

    public function testIncompleteBody()
    {
        if (version_compare(phpversion(), '7.4', '>=')) {
            $this::expectException(\HTTP_Request2_Exception::class);
        }
        parent::testIncompleteBody();
    }
}
?>