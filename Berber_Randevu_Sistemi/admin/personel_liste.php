<?php
require_once '../config.php';
yetki_kontrol(['admin']);

// Mesajları yönet
if (isset($_SESSION['mesaj'])) {
    $mesaj = $_SESSION['mesaj'];
    unset($_SESSION['mesaj']);
}
if (isset($_SESSION['hata'])) {
    $hata = $_SESSION['hata'];
    unset($_SESSION['hata']);
}

// Personel ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['personel_ekle'])) {
    try {
        $sifre = password_hash($_POST['sifre'], PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("CALL personel_ekle(?, ?, ?, ?, ?, @sonuc)");
        $stmt->execute([
            $_POST['ad'],
            $_POST['soyad'],
            $_POST['email'],
            $sifre,
            $_POST['telefon']
        ]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        
        if (strpos($sonuc['sonuc'], 'Hata:') === 0 || strpos($sonuc['sonuc'], 'başarıyla') === false) {
            throw new Exception($sonuc['sonuc']);
        }
        
        $_SESSION['mesaj'] = $sonuc['sonuc'];
    } catch (Exception $e) {
        $_SESSION['hata'] = "Personel eklenemedi: " . $e->getMessage();
    }
    header('Location: personel_liste.php');
    exit;
}

// Personel silme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['personel_sil'])) {
    try {
        $stmt = $db->prepare("CALL personel_sil(?, @sonuc)");
        $stmt->execute([$_POST['personel_id']]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        
        if (strpos($sonuc['sonuc'], 'Hata:') === 0) {
            throw new Exception($sonuc['sonuc']);
        }
        
        $_SESSION['mesaj'] = $sonuc['sonuc'];
    } catch (Exception $e) {
        $_SESSION['hata'] = $e->getMessage();
    }
    header('Location: personel_liste.php');
    exit;
}

// Personel listesi (adminler hariç)
$personeller = $db->query("CALL personel_liste_admin_haric")->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli - Personel Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table-responsive { max-height: 500px; overflow-y: auto; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Paneli</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Randevular</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="personel_liste.php">Personel Yönetimi</a>
                    </li>
					<li class="nav-item">
                        <a class="nav-link" href="profil.php">Profili Güncelle</a>
                    </li>
                </ul>
                <span class="navbar-text me-3">
                    <?php echo htmlspecialchars($_SESSION['kullanici']['ad']." ".$_SESSION['kullanici']['soyad']); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($mesaj)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $mesaj; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($hata)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $hata; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Yeni Personel Ekle</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Ad</label>
                                <input type="text" name="ad" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Soyad</label>
                                <input type="text" name="soyad" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Şifre</label>
                                <input type="password" name="sifre" class="form-control" required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telefon</label>
                                <input type="tel" name="telefon" class="form-control">
                            </div>
                            <button type="submit" name="personel_ekle" class="btn btn-primary w-100">
                                <i class="bi bi-person-plus"></i> Personel Ekle
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Personel Listesi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ad Soyad</th>
                                        <th>Email</th>
                                        <th>Telefon</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($personeller as $personel): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($personel['ad'].' '.$personel['soyad']); ?></td>
                                            <td><?php echo htmlspecialchars($personel['email']); ?></td>
                                            <td><?php echo htmlspecialchars($personel['telefon']); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Bu personeli ve tüm verilerini silmek istediğinize emin misiniz?');">
                                                    <input type="hidden" name="personel_id" value="<?php echo $personel['kullanici_id']; ?>">
                                                    <button type="submit" name="personel_sil" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i> Sil
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>