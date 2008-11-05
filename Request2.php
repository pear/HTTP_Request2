<?php
/**
 * Class representing a HTTP request
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2008, Alexey Borzov <avb@php.net>
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
 * @version    CVS: $Id$
 * @link       http://pear.php.net/package/HTTP_Request2
 */

/**
 * A class representing an URL as per RFC 3986.
 */
require_once 'Net/URL2.php';

/**
 * Exception class for HTTP_Request2 package
 */ 
require_once 'HTTP/Request2/Exception.php';

/**
 * Class representing a HTTP request
 *
 * @category   HTTP
 * @package    HTTP_Request2
 * @author     Alexey Borzov <avb@php.net>
 * @version    Release: @package_version@
 * @link       http://tools.ietf.org/html/rfc2616#section-5
 */
class HTTP_Request2 implements SplSubject
{
   /**#@+
    * Constants for HTTP request methods
    *
    * @link http://tools.ietf.org/html/rfc2616#section-5.1.1
    */
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_GET     = 'GET';
    const METHOD_HEAD    = 'HEAD';
    const METHOD_POST    = 'POST';
    const METHOD_PUT     = 'PUT';
    const METHOD_DELETE  = 'DELETE';
    const METHOD_TRACE   = 'TRACE';
    const METHOD_CONNECT = 'CONNECT';
   /**#@-*/

   /**#@+
    * Constants for HTTP authentication schemes 
    *
    * @link http://tools.ietf.org/html/rfc2617
    */
    const AUTH_BASIC  = 'basic';
    const AUTH_DIGEST = 'digest';
   /**#@-*/

   /**
    * Fileinfo magic database resource
    * @var  resource
    * @see  detectMimeType()
    */
    private static $_fileinfoDb;

   /**
    * Observers attached to the request (instances of SplObserver)
    * @var  array
    */
    protected $observers = array();

   /**
    * Request URL
    * @var  Net_URL2
    */
    protected $url;

   /**
    * Request method
    * @var  string
    */
    protected $method = self::METHOD_GET;

   /**
    * Authentication data
    * @var  array
    * @see  getAuth()
    */
    protected $auth;

   /**
    * Request headers
    * @var  array
    */
    protected $headers = array();

   /**
    * Cobfiguration parameters
    * @var  array
    * @see  setConfig()
    */
    protected $config = array(
        'adapter'      => 'HTTP_Request2_Adapter_Socket',
        'timeout'      => 10,
        'use_brackets' => true
    );

   /**
    * Last event in request / response handling, intended for observers
    * @var  array
    * @see  getLastEvent()
    */
    protected $lastEvent = array(
        'name' => 'start',
        'data' => null
    );

   /**
    * Request body
    * @var  string|resource
    * @see  setBody()
    */
    protected $body;

   /**
    * Array of POST parameters
    * @var  array
    */
    protected $postParams = array();

   /**
    * Array of file uploads (for multipart/form-data POST requests) 
    * @var  array
    */
    protected $files = array();


   /**
    * Constructor. Can set request URL, method and configuration array.
    *
    * Also sets a default value for User-Agent header. 
    *
    * @param    string|Net_Url2     Request URL
    * @param    string              Request method
    * @param    array               Configuration for this Request instance
    */
    public function __construct($url = null, $method = self::METHOD_GET, array $config = array())
    {
        if (!empty($url)) {
            $this->setUrl($url);
        }
        if (!empty($method)) {
            $this->setMethod($method);
        }
        $this->setConfig($config);
        $this->setHeader('user-agent', 'HTTP_Request2/@package_version@ ' .
                         '(http://pear.php.net/package/http_request2) ' .
                         'PHP/' . phpversion());
    }

   /**
    * Sets the URL for this request
    *
    * If the URL has userinfo part (username & password) these will be removed
    * and converted to auth data.
    *
    * @param    string|Net_URL2 Request URL
    * @return   HTTP_Request2
    * @throws   HTTP_Request2_Exception
    */
    public function setUrl($url)
    {
        if (is_string($url)) {
            $url = new Net_URL2($url);
        }
        if (!$url instanceof Net_URL2) {
            throw new HTTP_Request2_Exception('Parameter is not a valid HTTP URL');
        }
        // URL contains username / password?
        if ($url->getUserinfo()) {
            $username = $url->getUser();
            $password = $url->getPassword();
            $this->setAuth(rawurldecode($username), $password? rawurldecode($password): '');
            $url->setUserinfo('');
        }
        $this->url = $url;

        return $this;
    }

   /**
    * Returns the request URL
    *
    * @return   Net_URL2
    */
    public function getUrl()
    {
        return $this->url;
    }

   /**
    * Sets the request method
    *
    * @param    string
    * @return   HTTP_Request2
    * @throws   HTTP_Request2_Exception if the method name is invalid
    */
    public function setMethod($method)
    {
        // Method name should be a token: http://tools.ietf.org/html/rfc2616#section-5.1.1
        if (preg_match('![\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]!', $method)) {
            throw new HTTP_Request2_Exception("Invalid request method '{$method}'");
        }
        $this->method = $method;

        return $this;
    }

   /**
    * Returns the request method
    *
    * @return   string
    */
    public function getMethod()
    {
        return $this->method;
    }

   /**
    * Sets the configuration parameters
    *
    * @param    array   array of the form ('param name' => 'param value')
    * @return   HTTP_Request2
    * @throws   HTTP_Request2_Exception If the parameter is unknown
    */
    public function setConfig(array $config = array())
    {
        foreach ($config as $k => $v) {
            if (!array_key_exists($k, $this->config)) {
                throw new HTTP_Request2_Exception("Unknown configuration parameter '{$k}'");
            }
            $this->config[$k] = $v;
        }

        return $this;
    }

   /**
    * Returns the value of the configuration parameter
    *
    * @return   mixed
    * @throws   HTTP_Request2_Exception If the parameter is unknown
    */
    public function getConfigValue($name)
    {
        if (!array_key_exists($name, $this->config)) {
            throw new HTTP_Request2_Exception("Unknown configuration parameter '{$name}'");
        }
        return $this->config[$name];
    }

   /**
    * Sets the autentification data
    *
    * @param    string  user name
    * @param    string  password
    * @param    string  authentication scheme
    * @return   HTTP_Request2
    */ 
    public function setAuth($user, $password = '', $scheme = self::AUTH_BASIC)
    {
        if (empty($user)) {
            $this->auth = null;
        } else {
            $this->auth = array(
                'user'     => (string)$user,
                'password' => (string)$password,
                'scheme'   => $scheme
            );
        }

        return $this;
    }

   /**
    * Returns the authentication data
    *
    * The array has the keys 'user', 'password' and 'scheme', where 'scheme'
    * is one of the HTTP_Request2::AUTH_* constants.
    *
    * @return   array
    */
    public function getAuth()
    {
        return $this->auth;
    }

   /**
    * Sets request header(s)
    *
    * The first parameter may be either a full header string 'header: value' or
    * header name. In the former case $value parameter is ignored, in the latter 
    * the header's value will either be set to $value or the header will be
    * removed if $value is null. The first parameter can also be an array of
    * headers, in that case method will be called recursively.
    *
    * Note that headers are treated case insensitively as per RFC 2616.
    * 
    * <code>
    * $req->setHeader('Foo: Bar'); // sets the value of 'Foo' header to 'Bar'
    * $req->setHeader('FoO', 'Baz'); // sets the value of 'Foo' header to 'Baz'
    * $req->setHeader(array('foo' => 'Quux')); // sets the value of 'Foo' header to 'Quux'
    * $req->setHeader('FOO'); // removes 'Foo' header from request
    * </code>
    *
    * @param    string|array    header name, header string ('Header: value')
    *                           or an array of headers
    * @param    string|null     header value, header will be removed if null
    * @return   HTTP_Request2
    * @throws   HTTP_Request2_Exception
    */
    public function setHeader($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                if (is_string($k)) {
                    $this->setHeader($k, $v);
                } else {
                    $this->setHeader($v);
                }
            }
        } else {
            if (!$value && strpos($name, ':')) {
                list($name, $value) = array_map('trim', explode(':', $name, 2));
            }
            // Header name should be a token: http://tools.ietf.org/html/rfc2616#section-4.2
            if (preg_match('![\x00-\x1f\x7f-\xff()<>@,;:\\\\"/\[\]?={}\s]!', $name)) {
                throw new HTTP_Request2_Exception("Invalid header name '{$name}'");
            }
            // Header names are case insensitive anyway
            $name = strtolower($name);
            if (!$value) {
                unset($this->headers[$name]);
            } else {
                $this->headers[$name] = $value;
            }
        }
        
        return $this;
    }

   /**
    * Returns the request headers
    *
    * The array is of the form ('header name' => 'header value'), header names
    * are lowercased
    *
    * @return   array
    */
    public function getHeaders()
    {
        return $this->headers;
    }

   /**
    * Appends a cookie to "Cookie:" header
    *
    * @param    string  cookie name
    * @param    string  cookie value
    * @return   HTTP_Request2
    * @throws   HTTP_Request2_Exception
    */
    public function addCookie($name, $value)
    {
        $cookie = $name . '=' . $value;
        // Disallowed characters: http://cgi.netscape.com/newsref/std/cookie_spec.html
        if (preg_match('/[\s,;]/', $cookie)) {
            throw new HTTP_Request2_Exception("Invalid cookie: '{$cookie}'");
        }
        $cookies = empty($this->headers['cookie'])? '': $this->headers['cookie'] . '; ';
        $this->setHeader('cookie', $cookies . $cookie);

        return $this;
    }

   /**
    * Sets the request body
    *
    * @param    string  Either a string with the body or filename containing body
    * @param    bool    Whether first parameter is a filename
    * @return   HTTP_Request2
    * @throws   HTTP_Request2_Exception
    */
    public function setBody($body, $isFilename = false)
    {
        if (!$isFilename) {
            $this->body = $body;
        } else {
            if (!($fp = @fopen($body, 'rb'))) {
                throw new HTTP_Request2_Exception("Cannot open file {$body}");
            }
            $this->body = $fp;
            if (empty($this->headers['content-type'])) {
                $this->setHeader('content-type', self::detectMimeType($body));
            }
        }

        return $this;
    }

   /**
    * Returns the request body
    *
    * @return   string|resource
    * @todo     Handle the multipart/form-data body
    */
    public function getBody()
    {
        if (self::METHOD_POST == $this->method && 
            (!empty($this->postParams) || !empty($this->files))
        ) {
            if ('application/x-www-form-urlencoded' == $this->headers['content-type']) {
                $body = http_build_query($this->postParams, '', '&');
                if (!$this->getConfigValue('use_brackets')) {
                    $body = preg_replace('/%5B\d+%5D=/', '=', $body);
                }
                return $body;

            } elseif ('multipart/form-data' == $this->headers['content-type']) {
                // handle multipart encoding, via extra class
                throw new HTTP_Request2_Exception('Not implemented');
            }
        }
        return $this->body;
    }

   /**
    * Adds a file to form-based file upload
    *
    * Used to emulate file upload via a HTML form. The method also sets
    * Content-Type of HTTP request to 'multipart/form-data'.
    *
    * If you just want to send the contents of a file as the body of HTTP
    * request you should use setBody() method.
    *
    * @param    string  name of file-upload field
    * @param    mixed   full name of local file
    * @param    string  filename to send in the request 
    * @param    mixed   content-type of file being uploaded
    * @return   HTTP_Request2
    * @throws   HTTP_Request2_Exception
    * @todo     Implement the method...
    */
    public function addUpload($fieldName, $filename, $sendFilename,
                              $contentType = 'application/octet-stream')
    {
        throw new HTTP_Request2_Exception('Not implemented');
    }

   /**
    * Adds POST parameter(s) to the request.
    *
    * @param    string|array    parameter name or array ('name' => 'value')
    * @param    mixed           parameter value (can be an array)
    * @return   HTTP_Request2
    */
    public function addPostParameter($name, $value = null)
    {
        if (!is_array($name)) {
            $this->postParams[$name] = $value;
        } else {
            foreach ($name as $k => $v) {
                $this->addPostParameter($k, $v);
            }
        }
        if (empty($this->headers['content-type'])) {
            $this->setHeader('content-type', 'application/x-www-form-urlencoded');
        }

        return $this;
    }

   /**
    * Attaches a new observer
    *
    * @param    SplObserver
    */
    public function attach(SplObserver $observer)
    {
        foreach ($this->_observers as $attached) {
            if ($attached === $observer) {
                return;
            }
        }
        $this->_observers[] = $observer;
    }

   /**
    * Detaches an existing observer
    *
    * @param    SplObserver
    */
    public function detach(SplObserver $observer)
    {
        foreach ($this->_observers as $key => $attached) {
            if ($attached === $observer) {
                unset($this->_observers[$key]);
                return;
            }
        }
    }

   /**
    * Notifies all observers
    */
    public function notify()
    {
        foreach ($this->_observers as $observer) {
            $observer->update($this);
        }
    }

   /**
    * Sets the last event
    *
    * Adapters should use this method to set the current state of the request
    * and notify the observers.
    *
    * @param    string  event name
    * @param    mixed   event data
    */
    public function setLastEvent($name, $data = null)
    {
        $this->lastEvent = array(
            'name' => $name,
            'data' => $data
        );
        $this->notify();
    }

   /**
    * Returns the last event
    *
    * Observers should use this method to access the last change in request.
    *
    * @return   array   The array has two keys: 'name' and 'data'
    */
    public function getLastEvent()
    {
        return $this->lastEvent;
    }

   /**
    * Sends the request and returns the response
    *
    * @throws   HTTP_Request2_Exception
    * @return   HTTP_Request2_Response
    * @todo     Implement the method...
    */
    public function send()
    {
        throw new HTTP_Request2_Exception('Not implemented');
    }

   /**
    * Tries to detect MIME type of a file
    *
    * The method will try to use fileinfo extension if it is available,
    * deprecated mime_content_type() function in the other case. If neither
    * works, default 'application/octet-stream' MIME type is returned
    *
    * @param    string  filename
    * @return   string  file MIME type
    */
    protected static function detectMimeType($filename)
    {
        // finfo extension from PECL available 
        if (function_exists('finfo_open')) {
            if (!isset(self::$_fileinfoDb)) {
                self::$_fileinfoDb = @finfo_open(FILEINFO_MIME);
            }
            if (self::$_fileinfoDb) { 
                $info = finfo_file(self::$_fileinfoDb, $filename);
            }
        }
        // (deprecated) mime_content_type function available
        if (empty($info) && function_exists('mime_content_type')) {
            return mime_content_type($filename);
        }
        return empty($info)? 'application/octet-stream': $info;
    }
}
?>