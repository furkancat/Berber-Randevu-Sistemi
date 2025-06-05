<?php
require_once '../config.php';
yetki_kontrol(['musteri']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['randevu_iptal'])) {
    try {
        $stmt = $db->prepare("CALL randevu_iptal_et(?, ?, @sonuc)");
        $stmt->execute([
            $_POST['randevu_id'],
            $_SESSION['kullanici']['kullanici_id']
        ]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        
        if (strpos($sonuc['sonuc'], 'Hata:') === 0) {
            throw new Exception($sonuc['sonuc']);
        }
        
        $_SESSION['mesaj'] = "Randevu başarıyla iptal edildi!";
    } catch (Exception $e) {
        $_SESSION['hata'] = "Randevu iptal edilemedi: " . $e->getMessage();
    }
    
    header('Location: randevularim.php');
    exit;
} else {
    header('Location: randevularim.php');
    exit;
}