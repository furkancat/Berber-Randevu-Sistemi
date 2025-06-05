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

// Personel listesi (adminler dahil)
$personeller = $db->query("CALL personel_listele()")->fetchAll();

// Randevu durum güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['randevu_durum'])) {
    try {
        $stmt = $db->prepare("CALL randevu_durum_guncelle(?, ?, @sonuc)");
        $stmt->execute([
            $_POST['randevu_id'],
            $_POST['durum_id']
        ]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        
        if (strpos($sonuc['sonuc'], 'Tamamlanmış') === 0) {
            throw new Exception($sonuc['sonuc']);
        }
        
        $_SESSION['mesaj'] = $sonuc['sonuc'];
    } catch (Exception $e) {
        $_SESSION['hata'] = $e->getMessage();
    }
    
    header('Location: index.php');
    exit;
}

// Kapalı saat ekleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saat_kapat'])) {
    try {
        // Tarih formatını dönüştür (d/m/Y -> Y-m-d)
        $tarih = DateTime::createFromFormat('d/m/Y', $_POST['tarih']);
        if (!$tarih) {
            throw new Exception("Geçersiz tarih formatı! Lütfen GG/AA/YYYY formatında girin.");
        }
        $tarih = $tarih->format('Y-m-d');
        
        $stmt = $db->prepare("CALL kapali_saat_ekle(?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['kullanici']['kullanici_id'],
            $tarih,
            $_POST['baslangic_saati'],
            $_POST['bitis_saati'],
            $_POST['aciklama']
        ]);
        
        $_SESSION['mesaj'] = "Kapalı saat başarıyla eklendi!";
    } catch (Exception $e) {
        $_SESSION['hata'] = "Hata: " . $e->getMessage();
    }
    header('Location: index.php');
    exit;
}

// Kapalı saat silme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saat_sil'])) {
    try {
        $stmt = $db->prepare("CALL kapali_saat_sil(?, ?, @sonuc)");
        $stmt->execute([
            $_POST['saat_id'],
            $_SESSION['kullanici']['kullanici_id']
        ]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['mesaj'] = $sonuc['sonuc'];
    } catch (Exception $e) {
        $_SESSION['hata'] = "Hata: " . $e->getMessage();
    }
    header('Location: index.php');
    exit;
}

// Kapalı saat güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saat_guncelle'])) {
    try {
        // Tarih formatını dönüştür (d/m/Y -> Y-m-d)
        $tarih = DateTime::createFromFormat('d/m/Y', $_POST['guncelle_tarih']);
        if (!$tarih) {
            throw new Exception("Geçersiz tarih formatı! Lütfen GG/AA/YYYY formatında girin.");
        }
        $tarih = $tarih->format('Y-m-d');
        
        $stmt = $db->prepare("CALL kapali_saat_guncelle(?, ?, ?, ?, ?, ?, @sonuc)");
        $stmt->execute([
            $_POST['guncelle_saat_id'],
            $_SESSION['kullanici']['kullanici_id'],
            $tarih,
            $_POST['guncelle_baslangic'],
            $_POST['guncelle_bitis'],
            $_POST['guncelle_aciklama']
        ]);
        
        $sonuc = $db->query("SELECT @sonuc AS sonuc")->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['mesaj'] = $sonuc['sonuc'];
    } catch (Exception $e) {
        $_SESSION['hata'] = "Hata: " . $e->getMessage();
    }
    header('Location: index.php');
    exit;
}

// Adminin randevularını getir (bugün ve sonrası)
$randevular = $db->prepare("CALL personel_randevulari(?)");
$randevular->execute([$_SESSION['kullanici']['kullanici_id']]);
$randevular = $randevular->fetchAll();

// Kapalı saatleri getir
try {
    $kapali_saatler = $db->prepare("CALL kapali_saat_listesi(?)");
    $kapali_saatler->execute([$_SESSION['kullanici']['kullanici_id']]);
    $kapali_saatler = $kapali_saatler->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['hata'] = "Kapalı saatler yüklenirken hata: " . $e->getMessage();
    $kapali_saatler = [];
}

// Randevu durum seçenekleri
$durumlar = $db->query("CALL randevu_durumlari_al()")->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli - <?php echo htmlspecialchars($_SESSION['kullanici']['ad']." ".$_SESSION['kullanici']['soyad']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .randevu-karti {
            border-left: 4px solid;
            margin-bottom: 10px;
        }
        .beklemede { border-color: #ffc107; }
        .onaylandi { border-color: #28a745; }
        .iptal { border-color: #dc3545; }
        .tamamlandi { border-color: #0d6efd; }
        .kapali-saat { background-color: #f8f9fa; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .modal-content { border-radius: 10px; }
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
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Randevu Yönetimi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Saat</th>
                                        <th>Müşteri</th>
                                        <th>Telefon</th>
                                        <th>Durum</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($randevular as $randevu): ?>
                                        <tr class="randevu-karti <?php echo strtolower($randevu['durum']); ?>">
                                            <td><?php echo date('d/m/Y', strtotime($randevu['tarih'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($randevu['baslangic_saati'])); ?>-<?php echo date('H:i', strtotime($randevu['bitis_saati'])); ?></td>
                                            <td><?php echo htmlspecialchars($randevu['musteri_ad']." ".$randevu['musteri_soyad']); ?></td>
                                            <td><?php echo htmlspecialchars($randevu['telefon']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $randevu['durum_id'] == 2 ? 'success' : 
                                                        ($randevu['durum_id'] == 1 ? 'warning' : 
                                                        ($randevu['durum_id'] == 5 ? 'danger' : 
                                                        ($randevu['durum_id'] == 4 ? 'info' : 'secondary'))); ?>">
                                                    <?php echo htmlspecialchars($randevu['durum']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($randevu['durum_id'] == 1 or $randevu['durum_id'] == 2 or $randevu['durum_id'] == 3): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="randevu_id" value="<?php echo $randevu['randevu_id']; ?>">
                                                        <select name="durum_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                                            <option value="<?php echo 1; ?>" <?php echo $randevu['durum_id'] == 1 ? 'selected' : ''; ?>>Beklemede</option>
                                                            <option value="<?php echo 2; ?>" <?php echo $randevu['durum_id'] == 2 ? 'selected' : ''; ?>>Onaylandı</option>
                                                            <option value="<?php echo 3; ?>" <?php echo $randevu['durum_id'] == 3 ? 'selected' : ''; ?>>Reddedildi</option>
                                                            <option value="<?php echo 4; ?>" <?php echo $randevu['durum_id'] == 4 ? 'selected' : ''; ?>>Tamamlandı</option>
                                                            <option value="<?php echo 5; ?>" <?php echo $randevu['durum_id'] == 5 ? 'selected' : ''; ?>>İptal Edildi</option>
                                                        </select>
                                                        <input type="hidden" name="randevu_durum" value="1">
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Kapalı Saat Yönetimi</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Tarih</label>
                                <input type="text" name="tarih" class="form-control" id="tarihPicker" placeholder="Gün/Ay/Yıl" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col">
                                    <label class="form-label">Başlangıç</label>
                                    <select name="baslangic_saati" class="form-select" required>
                                        <?php for ($i = 9; $i <= 23; $i++): ?>
                                            <option value="<?php echo sprintf('%02d:00:00', $i); ?>"><?php echo sprintf('%02d:00', $i); ?></option>
                                            <?php if ($i != 23): ?>
                                                <option value="<?php echo sprintf('%02d:30:00', $i); ?>"><?php echo sprintf('%02d:30', $i); ?></option>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label class="form-label">Bitiş</label>
                                    <select name="bitis_saati" class="form-select" required>
                                        <?php for ($i = 9; $i < 23; $i++): ?>
                                            <option value="<?php echo sprintf('%02d:30:00', $i); ?>"><?php echo sprintf('%02d:30', $i); ?></option>
                                            <option value="<?php echo sprintf('%02d:00:00', $i+1); ?>"><?php echo sprintf('%02d:00', $i+1); ?></option>
                                        <?php endfor; ?>
                                        <option value="23:30:00">23:30</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Açıklama (Opsiyonel)</label>
                                <input type="text" name="aciklama" class="form-control" placeholder="Toplantı, izin vb.">
                            </div>
                            <button type="submit" name="saat_kapat" class="btn btn-warning w-100">
                                <i class="bi bi-calendar-x"></i> Saati Kapat
                            </button>
                        </form>
                        
                        <hr>
                        
                        <h5 class="mt-3">Kapalı Saatler</h5>
                        <?php if (!empty($kapali_saatler)): ?>
                            <div class="list-group">
                                <?php foreach ($kapali_saatler as $saat): ?>
                                    <div class="list-group-item kapali-saat">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo date('d.m.Y', strtotime($saat['tarih'])); ?></strong>
                                                <span class="ms-2"><?php echo substr($saat['baslangic_saati'], 0, 5); ?>-<?php echo substr($saat['bitis_saati'], 0, 5); ?></span>
                                            </div>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#guncelleModal" 
                                                    data-id="<?php echo $saat['kapali_saat_id']; ?>"
                                                    data-tarih="<?php echo date('d/m/Y', strtotime($saat['tarih'])); ?>"
                                                    data-baslangic="<?php echo substr($saat['baslangic_saati'], 0, 5); ?>"
                                                    data-bitis="<?php echo substr($saat['bitis_saati'], 0, 5); ?>"
                                                    data-aciklama="<?php echo htmlspecialchars($saat['aciklama']); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="saat_id" value="<?php echo $saat['kapali_saat_id']; ?>">
                                                    <button type="submit" name="saat_sil" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <?php if (!empty($saat['aciklama'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($saat['aciklama']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">Henüz kapalı saat eklenmemiş.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kapalı Saat Güncelleme Modal -->
    <div class="modal fade" id="guncelleModal" tabindex="-1" aria-labelledby="guncelleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="guncelleModalLabel">Kapalı Saati Güncelle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="guncelle_saat_id" id="guncelleSaatId">
                        <div class="mb-3">
                            <label class="form-label">Tarih</label>
                            <input type="text" name="guncelle_tarih" class="form-control" id="guncelleTarihPicker" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Başlangıç</label>
                                <select name="guncelle_baslangic" class="form-select" id="guncelleBaslangic" required>
                                    <?php for ($i = 9; $i <= 23; $i++): ?>
                                        <option value="<?php echo sprintf('%02d:00:00', $i); ?>"><?php echo sprintf('%02d:00', $i); ?></option>
                                        <?php if ($i != 23): ?>
                                            <option value="<?php echo sprintf('%02d:30:00', $i); ?>"><?php echo sprintf('%02d:30', $i); ?></option>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label">Bitiş</label>
                                <select name="guncelle_bitis" class="form-select" id="guncelleBitis" required>
                                    <?php for ($i = 9; $i < 23; $i++): ?>
                                        <option value="<?php echo sprintf('%02d:30:00', $i); ?>"><?php echo sprintf('%02d:30', $i); ?></option>
                                        <option value="<?php echo sprintf('%02d:00:00', $i+1); ?>"><?php echo sprintf('%02d:00', $i+1); ?></option>
                                    <?php endfor; ?>
                                    <option value="23:30:00">23:30</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <input type="text" name="guncelle_aciklama" class="form-control" id="guncelleAciklama">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="saat_guncelle" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/tr.js"></script>
    <script>
        // Türkçe tarih seçici
        flatpickr("#tarihPicker", {
            locale: "tr",
            dateFormat: "d/m/Y",
            minDate: "today",
            disable: [
                function(date) {
                    return (date.getDay() === 0); // Pazar günlerini devre dışı bırak
                }
            ]
        });

        // Güncelleme modalı için tarih seçici
        flatpickr("#guncelleTarihPicker", {
            locale: "tr",
            dateFormat: "d/m/Y",
            minDate: "today"
        });

        // Saat seçicileri için basit mantık
        document.querySelector('select[name="baslangic_saati"]').addEventListener('change', function() {
            let baslangic = this.value;
            let bitisSelect = document.querySelector('select[name="bitis_saati"]');
            bitisSelect.value = baslangic.replace(/:00$/, ':30:00');
        });

        // Güncelleme modalını doldur
        var guncelleModal = document.getElementById('guncelleModal');
        guncelleModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            
            document.getElementById('guncelleSaatId').value = button.getAttribute('data-id');
            document.getElementById('guncelleTarihPicker').value = button.getAttribute('data-tarih');
            document.getElementById('guncelleBaslangic').value = button.getAttribute('data-baslangic') + ':00';
            document.getElementById('guncelleBitis').value = button.getAttribute('data-bitis') + ':00';
            document.getElementById('guncelleAciklama').value = button.getAttribute('data-aciklama');
        });
    </script>
</body>
</html>