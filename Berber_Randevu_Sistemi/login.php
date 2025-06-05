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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $sifre = $_POST['sifre'];
    
    $stmt = $db->prepare("CALL kullanici_email_ile_al(?)");
    $stmt->execute([$email]);
    $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($kullanici && password_verify($sifre, $kullanici['sifre'])) {
        $_SESSION['kullanici'] = $kullanici;
        
        switch ($kullanici['rol']) {
            case 'admin': header('Location: admin/index.php'); break;
            case 'personel': header('Location: personel/index.php'); break;
            case 'musteri': header('Location: musteri/index.php'); break;
        }
        exit;
    } else {
        $hata = "Geçersiz email veya şifre!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Berber Randevu - Giriş</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .login-form { max-width: 400px; margin: 50px auto; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: red; margin-top: 10px; }
		.register-link { text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="login-form">
        <h2>Giriş Yap</h2>
        <?php if (isset($hata)): ?>
            <div class="error"><?php echo $hata; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email:</label>
                <input type="text" name="email" required>
            </div>
            <div class="form-group">
                <label>Şifre:</label>
                <input type="password" name="sifre" required>
            </div>
            <button type="submit">Giriş Yap</button>
			
			<div class="register-link">
                Hesabınız yok mu? <a href="register.php">Kayıt ol</a>
            </div>
        </form>
    </div>
</body>
</html>