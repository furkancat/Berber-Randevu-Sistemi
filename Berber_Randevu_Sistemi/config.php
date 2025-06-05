<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'berber_randevu';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

function yetki_kontrol($gereken_roller) {
    if (!isset($_SESSION['kullanici']) || !in_array($_SESSION['kullanici']['rol'], $gereken_roller)) {
        header('Location: ../login.php');
        exit;
    }
}

function tarih_format($tarih) {
    return date('d.m.Y', strtotime($tarih));
}

function saat_format($saat) {
    return date('H:i', strtotime($saat));
}
?>