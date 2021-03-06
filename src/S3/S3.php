<?php

namespace Buuum;

class S3
{
    const ACL_PRIVATE = 'private';
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PUBLIC_READ_WRITE = 'public-read-write';
    const ACL_AUTHENTICATED_READ = 'authenticated-read';

    const STORAGE_CLASS_STANDARD = 'STANDARD';
    const STORAGE_CLASS_RRS = 'REDUCED_REDUNDANCY';
    const STORAGE_CLASS_STANDARD_IA = 'STANDARD_IA';

    /**
     * @var null
     */
    private static $accessKey = null;
    /**
     * @var null
     */
    private static $secretKey = null;

    /**
     * @var mixed
     */
    private static $bucket = false;

    /**
     * @var string
     */
    private static $acl = self::ACL_PUBLIC_READ;
    /**
     * @var string
     */
    private static $storage = self::STORAGE_CLASS_STANDARD;

    /**
     * @var array
     */
    private static $request = [
        'method' => '',
        'bucket' => '',
        'uri'    => ''
    ];

    /**
     * @var string
     */
    public static $endpoint = 's3.amazonaws.com';

    /**
     * @param $accessKey
     * @param $secretKey
     * @param $bucket
     */
    public static function setAuth($accessKey, $secretKey, $bucket = false)
    {
        self::$accessKey = $accessKey;
        self::$secretKey = $secretKey;
        if ($bucket) {
            self::setBucket($bucket);
        }
    }

    /**
     * @return bool
     */
    public static function hasAuth()
    {
        return (self::$accessKey !== null && self::$secretKey !== null);
    }

    /**
     * @param $acl
     */
    public static function setAcl($acl)
    {
        self::$acl = $acl;
    }

    /**
     * @param $storage
     */
    public static function setStorage($storage)
    {
        self::$storage = $storage;
    }

    /**
     * @param $bucket
     */
    public static function setBucket($bucket)
    {
        if (substr($bucket, -1) == '/') {
            $bucket = substr($bucket, 0, -1);
        }
        self::$bucket = $bucket;
    }

    /**
     * @return mixed
     */
    public static function getBucket()
    {
        return self::$bucket;
    }

    /**
     * @return array|bool
     */
    public static function listBuckets()
    {
        self::$request = [
            'method' => 'GET',
            'bucket' => '',
            'uri'    => ''
        ];

        $response = self::getResponse();

        if ($response->error) {
            return false;
        }

        $response->body = simplexml_load_string($response->body);

        $results = [];
        foreach ($response->body->Buckets->Bucket as $b) {
            $results[] = (string)$b->Name;
        }

        return $results;
    }

    /**
     * @param $bucket
     * @return \stdClass
     */
    public static function putBucket($bucket)
    {
        self::$request = [
            'method' => 'PUT',
            'bucket' => self::getBucketName($bucket),
            'uri'    => ''
        ];

        return self::getResponse();
    }

    /**
     * @param $bucket
     * @return \stdClass
     */
    public static function deleteBucket($bucket)
    {
        self::$request = [
            'method' => 'DELETE',
            'bucket' => self::getBucketName($bucket),
            'uri'    => ''
        ];

        return self::getResponse();
    }

    /**
     * @param $bucket
     * @param $uri
     * @return \stdClass
     */
    public static function deleteObject($bucket, $uri)
    {
        self::$request = [
            'method' => 'DELETE',
            'bucket' => self::getBucketName($bucket),
            'uri'    => $uri
        ];

        return self::getResponse();
    }

    /**
     * @param $bucket
     * @param $uri
     * @return \stdClass
     */
    public static function getObject($bucket, $uri)
    {
        self::$request = [
            'method' => 'GET',
            'bucket' => self::getBucketName($bucket),
            'uri'    => $uri
        ];

        return self::getResponse();
    }

    /**
     * @param $url
     * @param $uri
     * @param $bucket
     * @param array $requestHeaders
     * @return \stdClass
     */
    public static function putObjectUrl($url, $uri, $bucket, $requestHeaders = [])
    {
        $string = self::getImg($url);
        return self::putObjectString($string, $uri, $bucket, $requestHeaders);
    }

    /**
     * @param $string
     * @param $uri
     * @param $bucket
     * @param array $requestHeaders
     * @return \stdClass
     */
    public static function putObjectString($string, $uri, $bucket, $requestHeaders = [])
    {
        if (extension_loaded('fileinfo')) {
            $file_info = new \finfo(FILEINFO_MIME_TYPE);
            $requestHeaders['Content-Type'] = $file_info->buffer($string);
        }
        if (empty($requestHeaders['Content-Type'])) {
            $requestHeaders['Content-Type'] = 'text/plain';
        }
        return self::putObject($string, $uri, $bucket, $requestHeaders);
    }

    /**
     * @param $file
     * @param $uri
     * @param $bucket
     * @param array $requestHeaders
     * @return \stdClass
     */
    public static function putObject($file, $uri, $bucket, $requestHeaders = [])
    {

        self::$request = [
            'method' => 'PUT',
            'bucket' => self::getBucketName($bucket),
            'uri'    => $uri
        ];

        return self::getResponse($file, $requestHeaders);

    }

    /**
     * @param bool $sourcefile
     * @param array $headers
     * @return \stdClass
     */
    private static function getResponse($sourcefile = false, $headers = [])
    {

        if (!self::hasAuth()) {
            return false;
        }

        $verb = self::$request['method'];
        $bucket = self::$request['bucket'];
        $uri = self::$request['uri'];
        $uri = $uri !== '' ? '/' . str_replace('%2F', '/', rawurlencode($uri)) : '/';

        $headers = array_merge([
            'Content-MD5'         => '',
            'Content-Type'        => '',
            'Date'                => gmdate('D, d M Y H:i:s T'),
            'Host'                => self::$endpoint,
            'x-amz-storage-class' => self::$storage,
            'x-amz-acl'           => self::$acl
        ], $headers);

        $resource = $uri;
        if ($bucket !== '') {
            if (self::dnsBucketName($bucket)) {
                $headers['Host'] = $bucket . '.' . self::$endpoint;
                $resource = '/' . $bucket . $uri;
            } else {
                $uri = '/' . $bucket . $uri;
                $resource = $uri;
            }
        }

        $response = new \stdClass();
        $response->error = false;
        $response->body = null;
        $response->headers = [];

        $url = 'http://' . $headers['Host'] . $uri;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, 'S3/php');
        curl_setopt($curl, CURLOPT_URL, $url);

        // PUT
        if ($verb == 'PUT') {

            if ($sourcefile) {

                if ($file = self::inputFile($sourcefile)) {
                    curl_setopt($curl, CURLOPT_PUT, true);
                    $fp = @fopen($file['file'], 'rb');
                    curl_setopt($curl, CURLOPT_INFILE, $fp);
                    curl_setopt($curl, CURLOPT_INFILESIZE, $file['size']);
                    $headers['Content-Type'] = $file['type'];
                } else {
                    $input = array(
                        'data'   => $sourcefile,
                        'size'   => strlen($sourcefile),
                        'md5sum' => base64_encode(md5($sourcefile, true))
                    );

                    $headers['Content-MD5'] = $input['md5sum'];

                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $input['data']);
                }


            } else {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
            }
        } elseif ($verb == 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $sendheaders = [];
        $amz = [];

        foreach ($headers as $header => $value) {
            if (strlen($value) > 0) {
                $sendheaders[] = $header . ': ' . $value;
                if (strpos($header, 'x-amz-') === 0) {
                    $amz[] = strtolower($header) . ':' . $value;
                }
            }
        }

        if (sizeof($amz) > 0) {
            usort($amz, array(__CLASS__, 'sortAmzHeaders'));
            $amz = "\n" . implode("\n", $amz);
        } else {
            $amz = '';
        }

        $sendheaders[] = 'Authorization: ' . self::getSignature(
                $verb . "\n" .
                $headers['Content-MD5'] . "\n" .
                $headers['Content-Type'] . "\n" .
                $headers['Date'] . $amz . "\n" .
                $resource
            );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $sendheaders);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        if ($data = curl_exec($curl)) {
            $response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $response->body = $data;
        } else {
            $response->error = [
                'code'     => curl_errno($curl),
                'message'  => curl_error($curl),
                'resource' => $resource
            ];
        }

        @curl_close($curl);

        if (isset($fp) && $fp !== false && is_resource($fp)) {
            fclose($fp);
        }

        return $response;
    }

    /**
     * @param $file
     * @return array|bool
     */
    private static function inputFile($file)
    {
        if (!@file_exists($file) || !is_file($file) || !is_readable($file)) {
            return false;
        }

        return [
            'file'   => $file,
            'size'   => filesize($file),
            'type'   => self::getMIMEType($file),
            'md5sum' => ''
        ];
    }

    /**
     * @param $file
     * @return string
     */
    private static function getMIMEType($file)
    {
        $exts = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'png'  => 'image/png',
            'ico'  => 'image/x-icon',
            'pdf'  => 'application/pdf',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
            'svg'  => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'swf'  => 'application/x-shockwave-flash',
            'zip'  => 'application/zip',
            'gz'   => 'application/x-gzip',
            'tar'  => 'application/x-tar',
            'bz'   => 'application/x-bzip',
            'bz2'  => 'application/x-bzip2',
            'rar'  => 'application/x-rar-compressed',
            'exe'  => 'application/x-msdownload',
            'msi'  => 'application/x-msdownload',
            'cab'  => 'application/vnd.ms-cab-compressed',
            'txt'  => 'text/plain',
            'asc'  => 'text/plain',
            'htm'  => 'text/html',
            'html' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'text/javascript',
            'xml'  => 'text/xml',
            'xsl'  => 'application/xsl+xml',
            'ogg'  => 'application/ogg',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/x-wav',
            'avi'  => 'video/x-msvideo',
            'mpg'  => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'mov'  => 'video/quicktime',
            'flv'  => 'video/x-flv',
            'php'  => 'text/x-php'
        ];

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (isset($exts[$ext])) {
            return $exts[$ext];
        }

        if (extension_loaded('fileinfo')) {
            $finfo = new \finfo(FILEINFO_MIME);
            $type = $finfo->file($file);
            $re = "@/(.*?);@";

            preg_match($re, $type, $matches);

            if (!empty($matches)) {
                $type = $matches[1];
            }

            if (isset($exts[$type])) {
                return $exts[$type];
            }
        }

        return 'application/octet-stream';
    }

    /**
     * @param $bucket
     * @return bool
     */
    private static function dnsBucketName($bucket)
    {
        if (strlen($bucket) > 63 || preg_match("/[^a-z0-9\.-]/", $bucket) > 0) {
            return false;
        }
        if (strstr($bucket, '-.') !== false) {
            return false;
        }
        if (strstr($bucket, '..') !== false) {
            return false;
        }
        if (!preg_match("/^[0-9a-z]/", $bucket)) {
            return false;
        }
        if (!preg_match("/[0-9a-z]$/", $bucket)) {
            return false;
        }
        return true;
    }

    /**
     * @param $a
     * @param $b
     * @return int
     */
    private static function sortAmzHeaders($a, $b)
    {
        $lenA = strpos($a, ':');
        $lenB = strpos($b, ':');
        $minLen = min($lenA, $lenB);
        $ncmp = strncmp($a, $b, $minLen);
        if ($lenA == $lenB) {
            return $ncmp;
        }
        if (0 == $ncmp) {
            return $lenA < $lenB ? -1 : 1;
        }
        return $ncmp;
    }

    /**
     * @param $string
     * @return string
     */
    private static function getSignature($string)
    {
        return 'AWS ' . self::$accessKey . ':' . self::getHash($string);
    }

    /**
     * @param $string
     * @return string
     */
    private static function getHash($string)
    {
        return base64_encode(extension_loaded('hash') ?
            hash_hmac('sha1', $string, self::$secretKey, true) : pack('H*', sha1(
                (str_pad(self::$secretKey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
                pack('H*', sha1((str_pad(self::$secretKey, 64, chr(0x00)) ^
                        (str_repeat(chr(0x36), 64))) . $string)))));
    }

    /**
     * @param $url
     * @return mixed
     */
    private static function getImg($url)
    {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        if (substr($url, 0, strlen('https://')) == 'https://') {
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /**
     * @param $bucket
     * @return string
     */
    private static function getBucketName($bucket)
    {
        if (substr($bucket, 0, 1) == '/') {
            $bucket = substr($bucket, 1);
        }
        if (self::$bucket) {
            $bucket = self::getBucket() . '/' . $bucket;
        }
        if (substr($bucket, -1) == '/') {
            $bucket = substr($bucket, 0, -1);
        }
        return $bucket;
    }
}