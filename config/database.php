
<?php


define('BASE_URL', '/buanajayarentama');

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bjr_inventory3';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die('Koneksi gagal: ' . mysqli_connect_error());
}