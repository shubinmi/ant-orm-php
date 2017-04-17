<?php

namespace AntOrm\Tests\Mysql;

use AntOrm\Storage\OrmStorage;

if (!$content = file_get_contents(__DIR__ . '/init/db-params.cache')) {
    die('You have to run init/init.php at first');
}
if (!$conf = json_decode($content)) {
    die('File init/db-params.cache has incorrect content. Remove it. Run init.php. Try again.');
}
try {
    $conn = new \mysqli($conf->h, $conf->u, $conf->p);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    if (mysqli_select_db($conn, 'ant_orm_tests')) {
        echo 'Use ant_orm_tests db' . PHP_EOL;
    } else {
        die('Can\'t select ant_orm_tests db: ' . $conn->error);
    }
} catch (\Exception $e) {
    $conn->close();
    die($e->getMessage());
}
$conn->close();

/** @noinspection PhpUnusedLocalVariableInspection */
$storage = new OrmStorage(
    'mysqli',
    [
        'host' => $conf->h,
        'user' => $conf->u,
        'pass' => $conf->p,
        'db'   => 'ant_orm_tests'
    ]
);