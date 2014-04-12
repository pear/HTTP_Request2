<?php
/**
 * An observer that saves response body to stream, possibly uncompressing it
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
 * @author    Delian Krustev <krustev@krustev.net>
 * @author    Alexey Borzov <avb@php.net>
 * @copyright 2008-2014 Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @link      http://pear.php.net/package/HTTP_Request2
 */

require_once 'HTTP/Request2/Response.php';

/**
 * An observer that saves response body to stream, possibly uncompressing it
 *
 * This Observer is written in compliment to pear's HTTP_Request2 in order to
 * avoid reading the whole response body in memory. Instead it writes the body
 * to a stream. If the body is transferred with content-encoding set to
 * "deflate" or "gzip" it is decoded on the fly.
 *
 * The constructor accepts an already opened (for write) stream (file_descriptor).
 * If the response is deflate/gzip encoded a "zlib.inflate" filter is applied
 * to the stream. When the body has been read from the request and written to
 * the stream ("receivedBody" event) the filter is removed from the stream.
 *
 * The "zlib.inflate" filter works fine with pure "deflate" encoding. It does
 * not understand the "deflate+zlib" and "gzip" headers though, so they have to
 * be removed prior to being passed to the stream. This is done in the "update"
 * method.
 *
 * It is also possible to limit the size of written extracted bytes by passing
 * "max_bytes" to the constructor. This is important because e.g. 1GB of
 * zeroes take about a MB when compressed.
 *
 * Exceptions are being thrown if data could not be written to the stream or
 * the written bytes have already exceeded the requested maximum. If the "gzip"
 * header is malformed or could not be parsed an exception will be thrown too.
 *
 * Example usage follows:
 *
 * <code>
 * require_once 'HTTP/Request2.php';
 * require_once 'HTTP/Request2/Observer/BodyDecodeAndWrite.php';
 *
 * #$inPath = 'http://carsten.codimi.de/gzip.yaws/daniels.html';
 * #$inPath = 'http://carsten.codimi.de/gzip.yaws/daniels.html?deflate=on';
 * $inPath = 'http://carsten.codimi.de/gzip.yaws/daniels.html?deflate=on&zlib=on';
 * #$outPath = "/dev/null";
 * $outPath = "delme";
 *
 * $stream = fopen($outPath, 'wb');
 * if (!$stream) {
 *     throw new Exception('fopen failed');
 * }
 *
 * $request = new HTTP_Request2(
 *     $inPath,
 *     HTTP_Request2::METHOD_GET,
 *     array(
 *         'store_body'        => false,
 *         'connect_timeout'   => 5,
 *         'timeout'           => 10,
 *         'ssl_verify_peer'   => true,
 *         'ssl_verify_host'   => true,
 *         'ssl_cafile'        => null,
 *         'ssl_capath'        => '/etc/ssl/certs',
 *         'max_redirects'     => 10,
 *         'follow_redirects'  => true,
 *         'strict_redirects'  => false
 *     )
 * );
 *
 * $observer = new HTTP_Request2_Observer_BodyDecodeAndWrite($stream, 9999999);
 * $request->attach($observer);
 *
 * $response = $request->send();
 *
 * fclose($stream);
 * echo "OK\n";
 * </code>
 *
 * @category HTTP
 * @package  HTTP_Request2
 * @author   Delian Krustev <krustev@krustev.net>
 * @author   Alexey Borzov <avb@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause License
 * @version  Release: @package_version@
 * @link     http://pear.php.net/package/HTTP_Request2
 */
class HTTP_Request2_Observer_BodyDecodeAndWrite implements SplObserver
{
    protected $stream;
    protected $stream_filter;
    protected $encoding;
    protected $flag_first_body_chunk = true;
    protected $response;
    protected $start_bytes = 0;
    protected $max_bytes;


    /**
     * Class constructor
     *
     * Note that there might be problems with max_bytes and files bigger
     * than 2 GB on 32bit platforms
     *
     * @param resource $stream    a stream (or file descriptor) opened for writing.
     * @param int      $max_bytes maximum bytes to write
     */
    public function __construct($stream, $max_bytes = null)
    {
        $this->stream = $stream;
        if ($max_bytes) {
            $this->max_bytes = $max_bytes;
            $this->start_bytes = ftell($this->stream);
        }
    }

    /**
     * Called when the request notifies us of an event.
     *
     * @param SplSubject $request The HTTP_Request2 instance
     *
     * @return void
     */
    public function update(SplSubject $request)
    {
        /* @var $request HTTP_Request2 */
        $event = $request->getLastEvent();

        switch ($event['name']) {
        case 'receivedHeaders':
            $this->response = $event['data'];
            $this->encoding = strtolower($this->response->getHeader('content-encoding'));
            break;

        case 'receivedBodyPart':
        case 'receivedEncodedBodyPart':
            if ($this->response->isRedirect()) {
                break;
            }
            $offset = 0;
            if ($this->flag_first_body_chunk) {
                if ($this->encoding === 'deflate' || $this->encoding === 'gzip') {
                    $this->stream_filter = stream_filter_append(
                        $this->stream, 'zlib.inflate', STREAM_FILTER_WRITE
                    );
                }
                if ($this->encoding === 'deflate') {
                    $header = unpack('n', substr($event['data'], 0, 2));
                    if (0 == $header[1] % 31) {
                        $offset = 2;
                    }
                }
                if ($this->encoding === 'gzip') {
                    $offset = HTTP_Request2_Response::parseGzipHeader($event['data'], false);
                }

                $this->flag_first_body_chunk = false;
            }

            $bytes = $offset ?
                fwrite($this->stream, substr($event['data'], $offset)) :
                fwrite($this->stream, $event['data']);

            if (false === $bytes) {
                throw new Exception('fwrite failed.');
            }

            if ($this->max_bytes
                && ftell($this->stream) - $this->start_bytes > $this->max_bytes
            ) {
                throw new Exception('Max bytes reached.');
            }
            break;

        case 'receivedBody':
            if ($this->stream_filter) {
                stream_filter_remove($this->stream_filter);
                $this->stream_filter = null;
            }
            break;
        }
    }
}
