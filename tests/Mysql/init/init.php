<?php

// Get args from commandline by default is (php init.php -h 127.0.0.1 -u root -p root)
$flags = getopt('h::u::p::');
$host  = "127.0.0.1";
$user  = "root";
$pass  = "root";
if (!empty($flags['h'])) {
    $host = $flags['h'];
}
if (!empty($flags['u'])) {
    $user = $flags['u'];
}
if (!empty($flags['p'])) {
    $pass = $flags['p'];
}
file_put_contents(
    __DIR__ . '/db-params.cache',
    json_encode(['h' => $host, 'u' => $user, 'p' => $pass])
);

// Create connection
$conn = new mysqli($host, $user, $pass);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS ant_orm_tests /*!40100 DEFAULT CHARACTER SET utf8 */;";
if ($conn->query($sql) === true) {
    echo "Database created successfully" . PHP_EOL;
} else {
    die("Error creating database: " . $conn->error);
}

// Choose db
if (mysqli_select_db($conn, 'ant_orm_tests')) {
    echo 'Use ant_orm_tests db' . PHP_EOL;
} else {
    die('Can\'t select ant_orm_tests db: ' . $conn->error);
}

// Import tables and data
if (!$commands = file_get_contents(__DIR__ . '/init.sql')) {
    die('Can\'t read or find db dump init.sql: ' . $conn->error);
}
if ($conn->multi_query($commands)) {
    echo 'Import db success' . PHP_EOL;
} else {
    die('Can\'t import data from init.sql: ' . $conn->error);
}
$conn->close();