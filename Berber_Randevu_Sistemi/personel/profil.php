<?php
require_once '../config.php';
yetki_kontrol(['admin', 'personel', 'musteri']);

$kullanici = $_SESSION['kullanici'];
$mesaj = '';
$hata = '';

// Profil bilgilerini güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['profil_guncelle'])) {
    try {
        $stmt = $db->prepare("CALL kullanici_guncelle(?, ?, ?, ?, ?, @sonuc)");
        $stmt->execute([
            $kullanici['kullanici_id'],
            $_POST['ad'],
            $_POST['soyad'],
            $_POST['email'],
            $_POST['telefon']
        ]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        
        // Session'ı güncelle
        $_SESSION['kullanici']['ad'] = $_POST['ad'];
        $_SESSION['kullanici']['soyad'] = $_POST['soyad'];
        $_SESSION['kullanici']['email'] = $_POST['email'];
        $_SESSION['kullanici']['telefon'] = $_POST['telefon'];
        
        $mesaj = $sonuc['sonuc'];
    } catch (PDOException $e) {
        $hata = "Hata: " . $e->getMessage();
    }
}

// Şifre değiştirme (GÜNCEL)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sifre_degistir'])) {
    try {
        // Mevcut şifreyi doğrula
        $stmt = $db->prepare("SELECT sifre FROM kullanicilar WHERE id = ?");
        $stmt->execute([$kullanici['kullanici_id']]);
        $kullanici_bilgisi = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$kullanici_bilgisi) {
            throw new Exception("Kullanıcı bulunamadı!");
        }
        
        // password_verify ile mevcut şifreyi kontrol et
        if (!password_verify($_POST['mevcut_sifre'], $kullanici_bilgisi['sifre'])) {
            throw new Exception("Mevcut şifre hatalı!");
        }
        
        // Yeni şifreleri karşılaştır
        if ($_POST['yeni_sifre'] !== $_POST['yeni_sifre_tekrar']) {
            throw new Exception("Yeni şifreler eşleşmiyor!");
        }
        
        // Yeni şifreyi hash'le
        $yeni_sifre_hash = password_hash($_POST['yeni_sifre'], PASSWORD_DEFAULT);
        
        // Veritabanını güncelle
        $stmt = $db->prepare("CALL sifre_degistir(?, ?, ?, @sonuc)");
        $stmt->execute([
            $kullanici['kullanici_id'],
            $kullanici_bilgisi['sifre'], // Mevcut hash'li şifre
            $yeni_sifre_hash             // Yeni hash'li şifre
        ]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        $mesaj = $sonuc['sonuc'];
        
    } catch (Exception $e) {
        $hata = $e->getMessage();
    }
}

// Kullanıcı bilgilerini yeniden al
$stmt = $db->prepare("CALL kullanici_al(?)");
$stmt->execute([$kullanici['kullanici_id']]);
$kullanici_bilgileri = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Yönetimi - <?php echo htmlspecialchars($kullanici['rol']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Berber Paneli</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Randevular</a>
                    </li>
					<li class="nav-item">
                        <a class="nav-link active" href="profil.php">Profili Güncelle</a>
                    </li>
                </ul>
                <span class="navbar-text me-3">
                    <?php echo htmlspecialchars($_SESSION['kullanici']['ad']." ".$_SESSION['kullanici']['soyad']); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light">Çıkış</a>
            </div>
        </div>
    </nav>
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Profil Bilgileri</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($mesaj): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($mesaj); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($hata): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($hata); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Ad</label>
                                    <input type="text" class="form-control" name="ad" 
                                           value="<?php echo htmlspecialchars($kullanici_bilgileri['ad']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Soyad</label>
                                    <input type="text" class="form-control" name="soyad" 
                                           value="<?php echo htmlspecialchars($kullanici_bilgileri['soyad']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($kullanici_bilgileri['email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" name="telefon" 
                                           value="<?php echo htmlspecialchars($kullanici_bilgileri['telefon'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="profil_guncelle" class="btn btn-primary">
                                        Bilgileri Güncelle
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Şifre Değiştir</h5>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Mevcut Şifre</label>
                                    <input type="password" class="form-control" name="mevcut_sifre" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Yeni Şifre</label>
                                    <input type="password" class="form-control" name="yeni_sifre" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Yeni Şifre (Tekrar)</label>
                                    <input type="password" class="form-control" name="yeni_sifre_tekrar" required>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="sifre_degistir" class="btn btn-warning">
                                        Şifre Değiştir
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="card-footer text-end">
                        <a href="index.php" class="btn btn-secondary">
                            Panele Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Şifre eşleşme kontrolü
        document.querySelector('form[name="sifre_degistir"]')?.addEventListener('submit', function(e) {
            const yeniSifre = document.querySelector('input[name="yeni_sifre"]').value;
            const tekrar = document.querySelector('input[name="yeni_sifre_tekrar"]').value;
            
            if (yeniSifre !== tekrar) {
                e.preventDefault();
                alert('Yeni şifreler eşleşmiyor!');
            }
        });
    </script>
</body>
</html>