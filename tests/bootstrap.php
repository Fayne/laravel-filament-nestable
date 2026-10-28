<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

putenv('COMPOSER_VENDOR_DIR='.dirname(__DIR__).'/vendor');
$_ENV['COMPOSER_VENDOR_DIR'] = dirname(__DIR__).'/vendor';

require dirname(__DIR__).'/vendor/autoload.php';

$skeleton = dirname(__DIR__).'/vendor/orchestra/testbench-core/laravel';
(new PackageManifest(
    new Filesystem,
    $skeleton,
    $skeleton.'/bootstrap/cache/packages.php',
))->build();
