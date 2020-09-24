# Changelog

## 2.4.2 - 2020-09-24
### Fixed
Socket adapter could prematurely end receiving the response body due to fread() call returning an empty string

## 2.4.1 - 2020-08-01
### Fixed
* Switch socket to blocking mode when enabling crypto, this fixes HTTPS requests
  through proxy with Socket adapter (issue #20)
* Add `.gitattributes` file to omit installing tests (issue #19)

## 2.4.0 - 2020-07-26

* Minimum required version is now PHP 5.6, as using older versions for HTTPS
  requests may be insecure
* Removed support for magic_quotes_runtime, as get_magic_quotes_runtime()
  was deprecated in PHP 7.4 and the functionality itself was disabled 
  since PHP 5.4 (bug #23839)
* Socket adapter now uses socket in non-blocking mode, as some configurations
  could have problems with timeouts in HTTPS requests (bug #21229)
* Fixed bogus size check error with gzipped responses larger than 4 GiB
  uncompressed (bug #21239)
* Use current &quot;Intermediate compatibility&quot; cipher list
* Updated Public Suffix List

The package is now 100% autoload-compatible, when installed with composer it
no longer uses include-path and does not contain require_once statements
