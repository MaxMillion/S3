Amazon S3 (REST) client for PHP
===============================

[![Packagist](https://img.shields.io/packagist/v/buuum/s3.svg)](https://packagist.org/packages/buuum/s3)
[![license](https://img.shields.io/github/license/mashape/apistatus.svg?maxAge=2592000)](#license)

## Install

### System Requirements

You need PHP >= 5.5.0 to use Buuum\S3 but the latest stable version of PHP is recommended.

### Composer

Buuum\S3 is available on Packagist and can be installed using Composer:

```
composer require buuum/s3
```

### Manually

You may use your own autoloader as long as it follows PSR-0 or PSR-4 standards. Just put src directory contents in your vendor directory.

## CONSTANTS

```php
const ACL_PRIVATE = 'private';
const ACL_PUBLIC_READ = 'public-read';
const ACL_PUBLIC_READ_WRITE = 'public-read-write';
const ACL_AUTHENTICATED_READ = 'authenticated-read';

const STORAGE_CLASS_STANDARD = 'STANDARD';
const STORAGE_CLASS_RRS = 'REDUCED_REDUNDANCY';
const STORAGE_CLASS_STANDARD_IA = 'STANDARD_IA';
```

## USAGE

### INITIALIZE
```php
S3::setAuth($awsAccessKey, $awsSecretKey, $bucket);
```

### SET and GET default bucket
```php
S3::setBucket($bucket);
S3::getBucket();
```

### SET ACL AND STORAGE (Optional, default acl = S3::ACL_PUBLIC_READ, storage = S3::STORAGE_CLASS_STANDARD)
```php
S3::setAcl(S3::ACL_PRIVATE);
S3::setStorage(S3::STORAGE_CLASS_STANDARD);
```

### UPLOADING OBJECTS

Put an object from $_FILES

```php
S3::putObject($_FILES['filename']['tmp_name'], $_FILES['filename']['name'], $bucketName);
```

Put an object from string

```php
S3::putObjectString(file_get_contents('bg.jpg'), 'bg.jpg', $bucketName);
```

Put an object from url

```php
$url = 'https://www.enterprise.es/content/dam/ecom/utilitarian/emea/business-rentals/business-rental-band.jpg.wrend.1280.720.jpeg';
S3::putObjectUrl($url, 'car.jpg', $bucketName);
```

### RETRIEVING OBJECTS

Get an object:

```php
$response = S3::getObject($bucketName, 'bg.jpg');
file_put_contents('bg.jpg', $response->body);
```

### DELETING OBJECTS

Delete an object:

```php
S3::deleteObject($bucketName, 'bg.jpg');
```

### BUCKETS

Get a list of buckets:

```php
$buckets = S3::listBuckets();
```

Create a bucket:
```php
S3::putBucket($bucketName);
```

Delete an empty bucket:
```php
S3::deleteBucket($bucketName);
```

## LICENSE

The MIT License (MIT)

Copyright (c) 2016

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.