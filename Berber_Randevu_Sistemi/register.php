<?php
require_once 'config.php';

// Eğer zaten giriş yapılmışsa uygun panele yönlendir
if (isset($_SESSION['kullanici'])) {
    switch ($_SESSION['kullanici']['rol']) {
        case 'admin': header('Location: admin/index.php'); break;
        case 'personel': header('Location: personel/index.php'); break;
        case 'musteri': header('Location: musteri/index.php'); break;
    }
    exit;
}

$hata = '';
$basari = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $email = trim($_POST['email']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];
    $telefon = trim($_POST['telefon']);
    
    // Validasyon
    if (empty($ad) || empty($soyad) || empty($email) || empty($sifre) || empty($sifre_tekrar)) {
        $hata = 'Lütfen tüm zorunlu alanları doldurunuz!';
    } elseif ($sifre !== $sifre_tekrar) {
        $hata = 'Şifreler uyuşmuyor!';
    } elseif (strlen($sifre) < 6) {
        $hata = 'Şifre en az 6 karakter olmalıdır!';
    } else {
        try {
            // Email kontrolü
            $stmt = $db->prepare("CALL kullanici_email_ile_al(?)");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $hata = 'Bu email adresi zaten kullanılıyor!';
            } else {
                // Şifreyi hashle
                $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
                
                // Kullanıcıyı kaydet (rolü otomatik olarak 'musteri' yapıyoruz)
                 $stmt = $db->prepare("CALL kullanici_ekle(?, ?, ?, ?, ?, 'musteri', @sonuc)");
				$stmt->execute([$ad, $soyad, $email, $sifre_hash, $telefon]);
				
				$sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
				
				if (strpos($sonuc['sonuc'], 'Hata:') === 0) {
					throw new Exception($sonuc['sonuc']);
				}
                
                $basari = 'Kayıt başarılı! Giriş yapabilirsiniz.';
                header("Refresh: 2; url=login.php");
            }
        } catch (PDOException $e) {
            $hata = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Berber Randevu - Kayıt Ol</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .register-form { max-width: 400px; margin: 50px auto; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        .required:after { content: " *"; color: red; }
        input[type="text"], input[type="email"], input[type="password"], input[type="tel"] { 
            width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px;
        }
        button { 
            background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; 
            cursor: pointer; width: 100%; font-size: 16px;
        }
        button:hover { background: #45a049; }
        .error { color: red; margin: 10px 0; padding: 10px; background: #ffebee; border-radius: 4px; }
        .success { color: green; margin: 10px 0; padding: 10px; background: #e8f5e9; border-radius: 4px; }
        .login-link { text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="register-form">
        <h2>Kayıt Ol</h2>
        
        <?php if (!empty($hata)): ?>
            <div class="error"><?php echo $hata; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($basari)): ?>
            <div class="success"><?php echo $basari; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="required">Ad:</label>
                <input type="text" name="ad" value="<?php echo isset($_POST['ad']) ? htmlspecialchars($_POST['ad']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label class="required">Soyad:</label>
                <input type="text" name="soyad" value="<?php echo isset($_POST['soyad']) ? htmlspecialchars($_POST['soyad']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label class="required">Email:</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label class="required">Şifre:</label>
                <input type="password" name="sifre" required minlength="6">
            </div>
            
            <div class="form-group">
                <label class="required">Şifre Tekrar:</label>
                <input type="password" name="sifre_tekrar" required minlength="6">
            </div>
            
            <div class="form-group">
                <label>Telefon:</label>
                <input type="tel" name="telefon" value="<?php echo isset($_POST['telefon']) ? htmlspecialchars($_POST['telefon']) : ''; ?>">
            </div>
            
            <button type="submit">Kayıt Ol</button>
            
            <div class="login-link">
                Zaten hesabınız var mı? <a href="login.php">Giriş yapın</a>
            </div>
        </form>
    </div>
</body>
</html>