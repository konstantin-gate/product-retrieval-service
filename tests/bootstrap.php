<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$fs = new Filesystem();

$cacheDir = dirname(__DIR__).'/storage/cache';
$counterDir = dirname(__DIR__).'/storage/counter';

if ($fs->exists($cacheDir)) {
    $fs->remove($cacheDir);
}
if ($fs->exists($counterDir)) {
    $fs->remove($counterDir);
}

$fs->mkdir($cacheDir);
$fs->mkdir($counterDir);
