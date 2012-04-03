<?php
/**
 * SOCKS5 proxy connection class
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2008-2012, Alexey Borzov <avb@php.net>
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
 * @category HTTP
 * @package  HTTP_Request2
 * @author   Alexey Borzov <avb@php.net>
 * @license  http://opensource.org/licenses/bsd-license.php New BSD License
 * @version  SVN: $Id$
 * @link     http://pear.php.net/package/HTTP_Request2
 */

/** Exception classes for HTTP_Request2 package */
require_once 'HTTP/Request2/Exception.php';

/**
 * SOCKS5 proxy connection class (used by Socket Adapter)
 *
 * @category HTTP
 * @package  HTTP_Request2
 * @author   Alexey Borzov <avb@php.net>
 * @license  http://opensource.org/licenses/bsd-license.php New BSD License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/HTTP_Request2
 * @link     http://pear.php.net/bugs/bug.php?id=19332
 * @link     http://tools.ietf.org/html/rfc1928
 */
class HTTP_Request2_SOCKS5
{
    /**
     * Connected socket
     * @var resource
     */
    protected $socket;

    /**
     * PHP warning messages raised during stream_socket_client() call
     * @var array
     */
    protected $connectionWarnings = array();

    /**
     * Constructor, tries to connect to a SOCKS5 proxy
     *
     * @param string $host       Proxy host
     * @param int    $port       Proxy port
     * @param int    $timeout    Connection timeout (seconds)
     * @param array  $sslOptions SSL context options
     * @param string $username   Proxy user name
     * @param string $password   Proxy password
     *
     * @throws HTTP_Request2_LogicException
     * @throws HTTP_Request2_ConnectionException
     * @throws HTTP_Request2_MessageException
     */
    public function __construct(
        $host, $port, $timeout = 10, array $sslOptions = array(),
        $username = null, $password = null
    ) {
        $context = stream_context_create();
        foreach ($sslOptions as $name => $value) {
            if (!stream_context_set_option($context, 'ssl', $name, $value)) {
                throw new HTTP_Request2_LogicException(
                    "Error setting SSL context option '{$name}'"
                );
            }
        }
        set_error_handler(array($this, 'connectionWarningsHandler'));
        $this->socket = stream_socket_client(
            "tcp://{$host}:{$port}", $errno, $errstr, $timeout,
            STREAM_CLIENT_CONNECT, $context
        );
        restore_error_handler();
        if (!$this->socket) {
            $error = $errstr ? $errstr : implode("\n", $this->connectionWarnings);
            throw new HTTP_Request2_ConnectionException(
                "Unable to connect to tcp://{$host}:{$port}. Error: {$error}", 0, $errno
            );
        }
        $this->performNegotiation();
    }

    /**
     * Performs a negotiation for auth method
     *
     * @param string $username Proxy user name
     * @param string $password Proxy password
     *
     * @throws HTTP_Request2_MessageException
     * @throws HTTP_Request2_ConnectionException
     */
    protected function performNegotiation($username = null, $password = null)
    {
        if (strlen($username)) {
            $request = pack('C4', 5, 2, 0, 2);
        } else {
            $request = pack('C3', 5, 1, 0);
        }
        if (false === fwrite($this->socket, $request)) {
            throw new HTTP_Request2_MessageException(
                'Error writing request to SOCKS5 proxy'
            );
        }
        if (false === ($response = fread($this->socket, 3))) {
            throw new HTTP_Request2_MessageException(
                'Error reading response from SOCKS5 proxy'
            );
        }
        $response = unpack('Cversion/Cmethod', $response);
        if (5 != $response['version']) {
            throw new HTTP_Request2_MessageException(
                'Invalid version received from SOCKS5 proxy: ' . $response['version'],
                HTTP_Request2_Exception::MALFORMED_RESPONSE
            );
        }
        switch ($response['method']) {
        case 2:
            $this->performAuthentication($username, $password);
        case 0:
            break;
        default:
            throw new HTTP_Request2_ConnectionException(
                "Connection rejected by proxy due to unsupported auth method"
            );
        }
    }

    /**
     * Performs username/password authentication for SOCKS5
     *
     * @param string $username Proxy user name
     * @param string $password Proxy password
     *
     * @throws HTTP_Request2_ConnectionException
     * @throws HTTP_Request2_MessageException
     * @link http://tools.ietf.org/html/rfc1929
     */
    protected function performAuthentication($username, $password)
    {
        $request  = pack('C2', 1, strlen($username)) . $username;
        $request .= pack('C', strlen($password)) . $password;

        if (false === fwrite($this->socket, $request)) {
            throw new HTTP_Request2_MessageException(
                'Error writing request to SOCKS5 proxy'
            );
        }
        if (false === ($response = fread($this->socket, 3))) {
            throw new HTTP_Request2_MessageException(
                'Error reading response from SOCKS5 proxy'
            );
        }
        $response = unpack('Cvn/Cstatus', $response);
        if (1 != $response['vn'] || 0 != $response['status']) {
            throw new HTTP_Request2_ConnectionException(
                'Connection rejected by proxy due to invalid username and/or password'
            );
        }
    }

    /**
     * Connects to a remote host via proxy
     *
     * @param string $remoteHost Remote host
     * @param int    $remotePort Remote port
     *
     * @return resource Connected socket
     * @throws HTTP_Request2_ConnectionException
     */
    public function connect($remoteHost, $remotePort)
    {
        $request = pack('C5', 0x05, 0x01, 0x00, 0x03, strlen($remoteHost))
                   . $remoteHost . pack('n', $remotePort);
        if (false === fwrite($this->socket, $request)) {
            throw new HTTP_Request2_MessageException(
                'Error writing request to SOCKS5 proxy'
            );
        }
        if (false === ($response = fread($this->socket, 1024))) {
            throw new HTTP_Request2_MessageException(
                'Error reading response from SOCKS5 proxy'
            );
        }
        $response = unpack('Cversion/Creply/Creserved', $response);
        if (5 != $response['version'] || 0 != $response['reserved']) {
            throw new HTTP_Request2_MessageException(
                'Invalid response received from SOCKS5 proxy',
                HTTP_Request2_Exception::MALFORMED_RESPONSE
            );
        } elseif (0 != $response['reply']) {
            throw new HTTP_Request2_ConnectionException(
                "Unable to connect to {$remoteHost}:{$remotePort} through SOCKS5 proxy",
                0, $response['reply']
            );
        }

        return $this->socket;
    }

    /**
     * Error handler to use during stream_socket_client() call
     *
     * @param int    $errno  error level
     * @param string $errstr error message
     *
     * @return bool
     * @see HTTP_Request2_Adapter_Socket::connectionWarningsHandler()
     */
    protected function connectionWarningsHandler($errno, $errstr)
    {
        if ($errno & E_WARNING) {
            array_unshift($this->connectionWarnings, $errstr);
        }
        return true;
    }
}
?>