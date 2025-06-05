<?php
session_start();

// Kullanıcı zaten giriş yapmışsa uygun panele yönlendir
if (isset($_SESSION['kullanici'])) {
    switch ($_SESSION['kullanici']['rol']) {
        case 'admin':
            header('Location: admin/index.php');
            exit;
        case 'personel':
            header('Location: personel/index.php');
            exit;
        case 'musteri':
            header('Location: musteri/index.php');
            exit;
        default:
            // Rol tanımlı değilse çıkış yaptır
            unset($_SESSION['kullanici']);
            break;
    }
}

// Giriş yapılmamışsa login sayfasına yönlendir
header('Location: login.php');
exit;
?>