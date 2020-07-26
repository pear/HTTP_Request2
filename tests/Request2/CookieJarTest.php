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
 * @copyright 2008-2020 Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      http://pear.php.net/package/HTTP_Request2
 */

/** Sets up includes */
require_once dirname(__DIR__) . '/TestHelper.php';

/**
 * Unit test for HTTP_Request2_CookieJar class
 */
class HTTP_Request2_CookieJarTest extends PHPUnit_Framework_TestCase
{
   /**
    * Cookie jar instance being tested
    * @var HTTP_Request2_CookieJar
    */
    protected $jar;

    protected function setUp()
    {
        $this->jar = new HTTP_Request2_CookieJar();
    }

   /**
    * Test that we can't store junk "cookies" in jar
    *
    * @dataProvider invalidCookieProvider
    * @expectedException HTTP_Request2_LogicException
    */
    public function testStoreInvalid($cookie)
    {
        $this->jar->store($cookie);
    }

    /**
     * Per feature requests, allow to ignore invalid cookies rather than throw exceptions
     *
     * @link http://pear.php.net/bugs/bug.php?id=19937
     * @link http://pear.php.net/bugs/bug.php?id=20401
     * @dataProvider invalidCookieProvider
     */
    public function testCanIgnoreInvalidCookies($cookie)
    {
        $this->jar->ignoreInvalidCookies(true);
        $this->assertFalse($this->jar->store($cookie));
    }

    /**
     * Ignore setting a cookie from "parallel" subdomain when relevant option is on
     *
     * @link http://pear.php.net/bugs/bug.php?id=20401
     */
    public function testRequest20401()
    {
        $this->jar->ignoreInvalidCookies(true);
        $response = HTTP_Request2_Adapter_Mock::createResponseFromFile(
            fopen(dirname(__DIR__) . '/_files/response_cookies', 'rb')
        );
        $setter   = new Net_URL2('http://pecl.php.net/');

        $this->assertFalse($this->jar->addCookiesFromResponse($response, $setter));
        $this->assertCount(3, $this->jar->getAll());
    }


   /**
    *
    * @dataProvider noPSLDomainsProvider
    */
    public function testDomainMatchNoPSL($requestHost, $cookieDomain, $expected)
    {
        $this->jar->usePublicSuffixList(false);
        $this->assertEquals($expected, $this->jar->domainMatch($requestHost, $cookieDomain));
    }

   /**
    *
    * @dataProvider PSLDomainsProvider
    */
    public function testDomainMatchPSL($requestHost, $cookieDomain, $expected)
    {
        $this->jar->usePublicSuffixList(true);
        $this->assertEquals($expected, $this->jar->domainMatch($requestHost, $cookieDomain));
    }

    public function testConvertExpiresToISO8601()
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('+1 day');

        $this->jar->store([
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => $dt->format(DateTime::COOKIE),
            'secure'  => false
        ]);
        $cookies = $this->jar->getAll();
        $this->assertEquals($cookies[0]['expires'], $dt->format(DateTime::ISO8601));
    }

    public function testProblem2038()
    {
        $this->jar->store([
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => 'Sun, 01 Jan 2040 03:04:05 GMT',
            'secure'  => false
        ]);
        $cookies = $this->jar->getAll();
        $this->assertEquals([[
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => '2040-01-01T03:04:05+0000',
            'secure'  => false
        ]], $cookies);
    }

    public function testStoreExpired()
    {
        $base = [
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'secure'  => false
        ];

        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('-1 day');
        $yesterday = $dt->format(DateTime::COOKIE);

        $dt->modify('+2 days');
        $tomorrow = $dt->format(DateTime::COOKIE);

        $this->jar->store($base + ['expires' => $yesterday]);
        $this->assertEquals(0, count($this->jar->getAll()));

        $this->jar->store($base + ['expires' => $tomorrow]);
        $this->assertEquals(1, count($this->jar->getAll()));
        $this->jar->store($base + ['expires' => $yesterday]);
        $this->assertEquals(0, count($this->jar->getAll()));
    }

   /**
    *
    * @dataProvider cookieAndSetterProvider
    */
    public function testGetDomainAndPathFromSetter($cookie, $setter, $expected)
    {
        $this->jar->store($cookie, $setter);
        $expected = array_merge($cookie, $expected);
        $cookies  = $this->jar->getAll();
        $this->assertEquals($expected, $cookies[0]);
    }

   /**
    *
    * @dataProvider cookieMatchProvider
    */
    public function testGetMatchingCookies($url, $expectedCount)
    {
        $cookies = [
            ['domain' => '.example.com', 'path' => '/', 'secure' => false],
            ['domain' => '.example.com', 'path' => '/', 'secure' => true],
            ['domain' => '.example.com', 'path' => '/path', 'secure' => false],
            ['domain' => '.example.com', 'path' => '/other', 'secure' => false],
            ['domain' => 'example.com', 'path' => '/', 'secure' => false],
            ['domain' => 'www.example.com', 'path' => '/', 'secure' => false],
            ['domain' => 'specific.example.com', 'path' => '/path', 'secure' => false],
            ['domain' => 'nowww.example.com', 'path' => '/', 'secure' => false],
        ];

        for ($i = 0; $i < count($cookies); $i++) {
            $this->jar->store($cookies[$i] + ['expires' => null, 'name' => "cookie{$i}", 'value' => "cookie_{$i}_value"]);
        }

        $this->assertEquals($expectedCount, count($this->jar->getMatching(new Net_URL2($url))));
    }

    public function testLongestPathFirst()
    {
        $cookie = [
            'name'    => 'foo',
            'domain'  => '.example.com',
        ];
        foreach (['/', '/specific/path/', '/specific/'] as $path) {
            $this->jar->store($cookie + ['path' => $path, 'value' => str_replace('/', '_', $path)]);
        }
        $this->assertEquals(
            'foo=_specific_path_; foo=_specific_; foo=_',
            $this->jar->getMatching(new Net_URL2('http://example.com/specific/path/file.php'), true)
        );
    }

    public function testSerializable()
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('+1 day');
        $cookie = ['domain' => '.example.com', 'path' => '/', 'secure' => false, 'value' => 'foo'];

        $this->jar->store($cookie + ['name' => 'session', 'expires' => null]);
        $this->jar->store($cookie + ['name' => 'long', 'expires' => $dt->format(DateTime::COOKIE)]);

        $newJar  = unserialize(serialize($this->jar));
        $cookies = $newJar->getAll();
        $this->assertEquals(1, count($cookies));
        $this->assertEquals('long', $cookies[0]['name']);

        $this->jar->serializeSessionCookies(true);
        $newJar = unserialize(serialize($this->jar));
        $this->assertEquals($this->jar->getAll(), $newJar->getAll());
    }

    public function testRemoveExpiredOnUnserialize()
    {
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('UTC'));
        $dt->modify('+2 seconds');

        $this->jar->store([
            'name'    => 'foo',
            'value'   => 'bar',
            'domain'  => '.example.com',
            'path'    => '/',
            'expires' => $dt->format(DateTime::COOKIE),
        ]);

        $serialized = serialize($this->jar);
        sleep(2);
        $newJar = unserialize($serialized);
        $this->assertEquals([], $newJar->getAll());
    }

    public static function invalidCookieProvider()
    {
        return [
            [[]],
            [['name' => 'foo']],
            [[
                'name'    => 'a name',
                'value'   => 'bar',
                'domain'  => '.example.com',
                'path'    => '/',
            ]],
            [[
                'name'    => 'foo',
                'value'   => 'a value',
                'domain'  => '.example.com',
                'path'    => '/',
            ]],
            [[
                'name'    => 'foo',
                'value'   => 'bar',
                'domain'  => '.example.com',
                'path'    => null,
            ]],
            [[
                'name'    => 'foo',
                'value'   => 'bar',
                'domain'  => null,
                'path'    => '/',
            ]],
            [[
                'name'    => 'foo',
                'value'   => 'bar',
                'domain'  => '.example.com',
                'path'    => '/',
                'expires' => 'invalid date',
            ]],
        ];
    }

    public static function noPSLdomainsProvider()
    {
        return [
            ['localhost', 'localhost', true],
            ['www.example.com', 'www.example.com', true],
            ['127.0.0.1', '127.0.0.1', true],
            ['127.0.0.1', '.0.0.1', false],
            ['www.example.com', '.example.com', true],
            ['deep.within.example.com', '.example.com', true],
            ['example.com', '.com', false],
            ['anotherexample.com', 'example.com', false],
            ['whatever.msk.ru', '.msk.ru', true],
            ['whatever.co.uk', '.co.uk', true],
            ['whatever.bd', '.whatever.bd', true],
            ['whatever.tokyo.jp', '.whatever.tokyo.jp', true],
            ['metro.tokyo.jp', '.metro.tokyo.jp', true],
            ['foo.bar', '.foo.bar', true]
        ];
    }

    public static function PSLdomainsProvider()
    {
        return [
            ['localhost', 'localhost', true],
            ['www.example.com', 'www.example.com', true],
            ['127.0.0.1', '127.0.0.1', true],
            ['127.0.0.1', '.0.0.1', false],
            ['www.example.com', '.example.com', true],
            ['deep.within.example.com', '.example.com', true],
            ['example.com', '.com', false],
            ['anotherexample.com', 'example.com', false],
            ['whatever.msk.ru', '.msk.ru', false],
            ['whatever.co.uk', '.co.uk', false],
            ['whatever.bd', '.whatever.bd', false],
            ['com.bn', '.com.bn', false],
            ['nic.tr', '.nic.tr', true],
            ['foo.bar', '.foo.bar', true]
        ];
    }

    public static function cookieAndSetterProvider()
    {
        return [
            [
                [
                    'name'    => 'foo',
                    'value'   => 'bar',
                    'domain'  => null,
                    'path'    => null,
                    'expires' => null,
                    'secure'  => false
                ],
                new Net_URL2('http://example.com/directory/file.php'),
                [
                    'domain'  => 'example.com',
                    'path'    => '/directory/'
                ]
            ],
            [
                [
                    'name'    => 'foo',
                    'value'   => 'bar',
                    'domain'  => '.example.com',
                    'path'    => null,
                    'expires' => null,
                    'secure'  => false
                ],
                new Net_URL2('http://example.com/path/to/file.php'),
                [
                    'path'    => '/path/to/'
                ]
            ],
            [
                [
                    'name'    => 'foo',
                    'value'   => 'bar',
                    'domain'  => null,
                    'path'    => '/',
                    'expires' => null,
                    'secure'  => false
                ],
                new Net_URL2('http://example.com/another/file.php'),
                [
                    'domain'  => 'example.com'
                ]
            ]
        ];
    }

    public static function cookieMatchProvider()
    {
        return [
            ['http://www.example.com/path/file.php', 4],
            ['https://www.example.com/path/file.php', 5],
            ['http://example.com/path/file.php', 3],
            ['http://specific.example.com/path/file.php', 4],
            ['http://specific.example.com/other/file.php', 3],
            ['http://another.example.com/another', 2]
        ];
    }
}
?>