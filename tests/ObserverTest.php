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
require_once __DIR__ . '/TestHelper.php';

// pear-package-only require_once __DIR__ . '/MockObserver.php';

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Unit test for subject-observer pattern implementation in HTTP_Request2
 */
class HTTP_Request2_ObserverTest extends TestCase
{
    public function testSetLastEvent()
    {
        $request  = new HTTP_Request2();
        $observer = new HTTP_Request2_MockObserver();
        $request->attach($observer);

        $request->setLastEvent('foo', 'bar');
        $this->assertEquals(1, $observer->calls);
        $this->assertEquals(['name' => 'foo', 'data' => 'bar'], $observer->event);

        $request->setLastEvent('baz');
        $this->assertEquals(2, $observer->calls);
        $this->assertEquals(['name' => 'baz', 'data' => null], $observer->event);
    }

    public function testAttachOnlyOnce()
    {
        $request   = new HTTP_Request2();
        $observer  = new HTTP_Request2_MockObserver();
        $observer2 = new HTTP_Request2_MockObserver();
        $request->attach($observer);
        $request->attach($observer2);
        $request->attach($observer);

        $request->setLastEvent('event', 'data');
        $this->assertEquals(1, $observer->calls);
        $this->assertEquals(1, $observer2->calls);
    }

    public function testDetach()
    {
        $request   = new HTTP_Request2();
        $observer  = new HTTP_Request2_MockObserver();
        $observer2 = new HTTP_Request2_MockObserver();

        $request->attach($observer);
        $request->detach($observer2); // should not be a error
        $request->setLastEvent('first');

        $request->detach($observer);
        $request->setLastEvent('second');
        $this->assertEquals(1, $observer->calls);
        $this->assertEquals(['name' => 'first', 'data' => null], $observer->event);
    }
}
?>
