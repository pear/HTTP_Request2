<?php
/**
 * Usage example for HTTP_Request2 package: uploading a file to rapidshare.com
 *
 * $Id$
 */

require_once 'HTTP/Request2.php';

// You'll probably want to change this
$filename = '/etc/passwd';

try {
    $request = new HTTP_Request2(
        'http://rapidshare.com/cgi-bin/rsapi.cgi?sub=nextuploadserver_v1'
    );
    $server  = $request->send()->getBody();
    if (!preg_match('/^(\\d+)$/', $server)) {
        throw new Exception("Invalid upload server: {$server}");
    }

    if (false === ($hash = @md5_file($filename))) {
        throw new Exception("Cannot calculate MD5 hash of '{$filename}'");
    }

    $uploader = new HTTP_Request2(
        "http://rs{$server}l3.rapidshare.com/cgi-bin/upload.cgi",
        HTTP_Request2::METHOD_POST
    );
    $uploader->addUpload('filecontent', $filename);
    $uploader->addPostParameter('rsapi_v1', '1');

    $response = $uploader->send()->getBody();
    if (!preg_match_all('/^(File[^=]+)=(.+)$/m', $response, $m, PREG_SET_ORDER)) {
        throw new Exception("Invalid response: {$response}");
    }
    $rspAry = array();
    foreach ($m as $item) {
        $rspAry[$item[1]] = $item[2];
    }
    if (empty($rspAry['File1.4'])) {
        throw new Exception("MD5 hash data not found in response");
    } elseif ($hash != strtolower($rspAry['File1.4'])) {
        throw new Exception("Upload failed, local MD5 is {$hash}, uploaded MD5 is {$rspAry['File1.4']}");
    }
    echo "Upload succeeded\nDownload link: {$rspAry['File1.1']}\nDelete link: {$rspAry['File1.2']}\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
