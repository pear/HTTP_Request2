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
 * Saves observed event names
 */
class HTTP_Request2_Adapter_EventSequenceObserver implements SplObserver
{
    private $_watched = [];

    public $sequence = [];

    public function __construct(array $watchedEvents = [])
    {
        if (!empty($watchedEvents)) {
            $this->_watched = $watchedEvents;
        }
    }

    #[ReturnTypeWillChange]
    public function update(SplSubject $subject)
    {
        /* @var $subject HTTP_Request2 */
        $event = $subject->getLastEvent();

        if ($event['name'] !== end($this->sequence)
            && (empty($this->_watched) || in_array($event['name'], $this->_watched, true))
        ) {
            $this->sequence[] = $event['name'];
        }
    }
}
?>
