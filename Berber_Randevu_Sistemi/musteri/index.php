<?php
require_once '../config.php';
yetki_kontrol(['musteri']);

// Mesajları yönet
if (isset($_SESSION['mesaj'])) {
    $mesaj = $_SESSION['mesaj'];
    unset($_SESSION['mesaj']);
}
if (isset($_SESSION['hata'])) {
    $hata = $_SESSION['hata'];
    unset($_SESSION['hata']);
}

// Personel listesi
$personeller = $db->query("CALL personel_listele()")->fetchAll();

// Randevu oluşturma
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['randevu_olustur'])) {
    try {
        $tarih_obj = DateTime::createFromFormat('d/m/Y', $_POST['tarih']);
        $tarih = $tarih_obj->format('Y-m-d');
        
		$sure = 30;
		
        // Prosedürü çağır
        $stmt = $db->prepare("CALL randevu_ekle(?, ?, ?, ?, ?, @randevu_id)");
        $stmt->execute([
            $_SESSION['kullanici']['kullanici_id'],
            $_POST['personel_id'],
            $sure,
            $tarih,
            $_POST['baslangic_saati']
        ]);
        
        // Oluşturulan randevu ID'sini al
        $randevu_id = $db->query("SELECT @randevu_id AS randevu_id")->fetch(PDO::FETCH_ASSOC);
        
        if (!$randevu_id || !isset($randevu_id['randevu_id'])) {
            throw new Exception("Randevu oluşturulurken bir hata oluştu!");
        }
        
        $_SESSION['mesaj'] = "Randevunuz başarıyla oluşturuldu!";
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['hata'] = $e->getMessage();
        header('Location: index.php');
        exit;
    }
}

// Uygun saatleri getir
$saat_durumlari = [];
if (isset($_GET['personel_id']) && isset($_GET['tarih'])) {
    try {
        $tarih_obj = DateTime::createFromFormat('d/m/Y', $_GET['tarih']);
        if (!$tarih_obj) {
            throw new Exception("Geçersiz tarih formatı!");
        }
		$start = new DateTime('09:00');
		$end = new DateTime('23:30');
		$interval = new DateInterval('PT30M');
		
		$bugun = new DateTime();
		$secilen_tarih = $tarih_obj->format('Y-m-d');
		$bugun_tarih = $bugun->format('Y-m-d');
		
		$period = new DatePeriod($start, $interval, $end);
		
		foreach ($period as $dt) {
			$saat_str = $dt->format('H:i');
			$saat_full = DateTime::createFromFormat('Y-m-d H:i', $secilen_tarih . ' ' . $saat_str);
		
			// Eğer bugünse ve saat geçmişse (veya 60 dakikadan az kaldıysa), listeye alma
			if ($secilen_tarih === $bugun_tarih) {
				$simdi = new DateTime();
				$fark_dakika = ($saat_full->getTimestamp() - $simdi->getTimestamp()) / 60;
				if ($fark_dakika < 60) {
					continue;
				}
			}
		
			$saat_durumlari[$saat_str] = [
				'saat' => $saat_str,
				'durum' => 'dolu',
				'aciklama' => 'Dolu'
			];
		}
		
		
        $tarih = $tarih_obj->format('Y-m-d');
        
        // MySQL fonksiyonunu çağır
        $stmt = $db->prepare("SELECT uygun_randevu_saatleri(?, ?, 30) AS saatler");
        $stmt->execute([$_GET['personel_id'], $tarih]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['saatler'])) {
			$musait_saatler = explode('|', rtrim($result['saatler'], '|'));
		
			$bugun = new DateTime();
			$secilen_tarih = $tarih_obj->format('Y-m-d');
			$bugun_tarih = $bugun->format('Y-m-d');
		
			foreach ($musait_saatler as $saat) {
				$kontrol_saati = DateTime::createFromFormat('Y-m-d H:i', $secilen_tarih . ' ' . $saat);
		
				if ($secilen_tarih == $bugun_tarih) {
					$simdiki_zaman = new DateTime();
					$fark = $simdiki_zaman->diff($kontrol_saati);
					$dakika_fark = ($fark->h * 60) + $fark->i;
					if ($kontrol_saati <= $simdiki_zaman || $dakika_fark < 60) {
						continue;
					}
				}
		
				// Musait olan saatleri güncelle
				if (isset($saat_durumlari[$saat])) {
					$saat_durumlari[$saat]['durum'] = 'musait';
					$saat_durumlari[$saat]['aciklama'] = '';
				}
			}
		}

        
        // Kapalı saatleri işaretle (fonksiyon bunu zaten kontrol ediyor ama açıklama için)
        $stmt_kapali = $db->prepare("CALL kapali_saatleri_getir(?, ?)");
        $stmt_kapali->execute([$_GET['personel_id'], $tarih]);
        $kapali_saatler = $stmt_kapali->fetchAll(PDO::FETCH_ASSOC);
        $stmt_kapali->closeCursor();
        
        foreach ($kapali_saatler as $kapali) {
			$baslangic = new DateTime($kapali['baslangic']);
			$bitis = new DateTime($kapali['bitis']);
			$interval = new DateInterval('PT30M');
			$kapali_period = new DatePeriod($baslangic, $interval, $bitis);
		
			foreach ($kapali_period as $dt) {
				$saat = $dt->format('H:i');
				$saat_durumlari[$saat] = [
					'saat' => $saat,
					'durum' => 'kapali',
					'aciklama' => $kapali['aciklama'] ?? 'Kapalı'
				];
			}
		}
        
        ksort($saat_durumlari);
        
    } catch (Exception $e) {
        $_SESSION['hata'] = $e->getMessage();
        header('Location: index.php');
        exit;
    }
}

// Müşterinin randevularını getir
$randevular = $db->prepare("CALL musteri_son5_randevu(?)");
$randevular->execute([$_SESSION['kullanici']['kullanici_id']]);
$randevular = $randevular->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli - Berber Randevu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .saat-secimi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0; }
        .saat-btn { padding: 10px; text-align: center; border-radius: 5px; position: relative; cursor: pointer; }
        .saat-btn.musait { background-color: #d4edda; border: 1px solid #c3e6cb; }
        .saat-btn.dolu { background-color: #f8d7da; border: 1px solid #f5c6cb; cursor: not-allowed; }
		.saat-btn.dolu:hover::after {
			content: attr(data-aciklama);
			position: absolute;
			bottom: 100%;
			left: 50%;
			transform: translateX(-50%);
			background: #333;
			color: #fff;
			padding: 5px 10px;
			border-radius: 4px;
			white-space: nowrap;
			z-index: 10;
		}

        .saat-btn.kapali { 
            background-color: #e2e3e5; 
            border: 1px solid #d6d8db; 
            color: #6c757d;
            cursor: not-allowed;
            position: relative;
            text-decoration: line-through;
        }
        .saat-btn.kapali:hover::after {
            content: attr(data-aciklama);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: #fff;
            padding: 5px 10px;
            border-radius: 4px;
            white-space: nowrap;
            z-index: 10;
        }
        .saat-btn.secili { background-color: #007bff; color: white; border-color: #007bff; }
        .randevu-karti { margin-bottom: 10px; }
        .beklemede { border-left: 4px solid #ffc107; }
        .onaylandi { border-left: 4px solid #28a745; }
        .iptal { border-left: 4px solid #dc3545; }
        .tamamlandi { border-left: 4px solid #0d6efd; }
        #tarih { background-color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Berber Randevu</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Randevu Al</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="randevularim.php">Randevularım</a>
                    </li>
					<li class="nav-item">
                        <a class="nav-link" href="profil.php">Profili Güncelle</a>
                    </li>
                </ul>
                <span class="navbar-text me-3">
                    Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['kullanici']['ad']); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light">Çıkış Yap</a>
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
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Yeni Randevu Oluştur</h5>
                    </div>
                    <div class="card-body">
                        <form id="randevuForm" method="GET" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="personel_id" class="form-label">Berber Seçin</label>
                                    <select class="form-select" id="personel_id" name="personel_id" required>
                                        <option value="">Berber Seçin</option>
                                        <?php foreach ($personeller as $personel): ?>
                                            <option value="<?php echo $personel['kullanici_id']; ?>" <?php echo isset($_GET['personel_id']) && $_GET['personel_id'] == $personel['kullanici_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($personel['ad_soyad']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="tarih" class="form-label">Tarih</label>
                                    <input type="text" class="form-control" id="tarih" name="tarih" value="<?php echo isset($_GET['tarih']) ? htmlspecialchars($_GET['tarih']) : ''; ?>" placeholder="Gün/Ay/Yıl" required>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="bi bi-calendar-check"></i> Uygun Saatleri Göster
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($saat_durumlari)): ?>
                            <hr>
                            <h5 class="mt-3"><?php echo htmlspecialchars($_GET['tarih']); ?> Tarihindeki Uygun Saatler</h5>
                            
                            <div class="saat-secimi" id="saatSecimi">
                                <?php foreach ($saat_durumlari as $saat): ?>
                                    <button type="button" class="saat-btn <?php echo $saat['durum']; ?>" 
                                        data-saat="<?php echo $saat['saat']; ?>"
                                        <?php if ($saat['durum'] != 'musait') echo 'disabled'; ?>
                                        <?php if ($saat['durum'] != 'musait') echo 'data-aciklama="'.htmlspecialchars($saat['aciklama']).'"'; ?>>
                                        <?php echo $saat['saat']; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <form id="randevuOnayForm" method="POST" action="">
                                <input type="hidden" name="personel_id" value="<?php echo htmlspecialchars($_GET['personel_id']); ?>">
                                <input type="hidden" name="tarih" value="<?php echo htmlspecialchars($_GET['tarih']); ?>">
                                <input type="hidden" name="baslangic_saati" id="seciliSaat">
                                <input type="hidden" name="bitis_saati" id="bitisSaati">
                                
                                <button type="submit" name="randevu_olustur" class="btn btn-success w-100 mt-3 py-2">
                                    <i class="bi bi-check-circle"></i> Randevu Oluştur
                                </button>
                            </form>
                        <?php elseif (isset($_GET['personel_id'])): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle"></i> Seçilen tarih için uygun randevu saati bulunamadı.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Son Randevularım</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($randevular)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Henüz randevunuz bulunmamaktadır.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($randevular as $randevu): ?>
                                    <div class="list-group-item randevu-karti <?php echo strtolower($randevu['durum']); ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($randevu['personel_ad'].' '.$randevu['personel_soyad']); ?></h6>
                                            <small class="text-<?php 
                                                echo $randevu['durum_id'] == 2 ? 'success' : 
                                                     ($randevu['durum_id'] == 1 ? 'warning' : 
                                                     ($randevu['durum_id'] == 5 ? 'secondary' : 'primary')); ?>">
                                                <?php echo htmlspecialchars($randevu['durum']); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1 small">
                                            <strong>Tarih:</strong> <?php echo date('d.m.Y', strtotime($randevu['tarih'])); ?><br>
                                            <strong>Saat:</strong> <?php echo date('H:i', strtotime($randevu['baslangic_saati'])); ?> - <?php echo date('H:i', strtotime($randevu['bitis_saati'])); ?>
                                        </p>
                                        <?php if ($randevu['durum_id'] == 1 || $randevu['durum_id'] == 2): ?>
												<form method="POST" action="randevu_iptal.php" onsubmit="return confirm('Bu randevuyu iptal etmek istediğinize emin misiniz?');">
													<input type="hidden" name="randevu_id" value="<?php echo $randevu['randevu_id']; ?>">
													<button type="submit" name="randevu_iptal" class="btn btn-sm btn-danger">
														<i class="bi bi-x-circle"></i> İptal Et
													</button>
												</form>
											<?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="randevularim.php" class="btn btn-outline-info w-100 mt-3">
                                <i class="bi bi-list-ul"></i> Tüm Randevularım
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/tr.js"></script>
    <script>
        // Tarih seçici (Türkçe)
        flatpickr("#tarih", {
            locale: "tr",
            dateFormat: "d/m/Y",
            minDate: "today",
            disable: [
                function(date) {
                    return (date.getDay() === 0); // Pazar günlerini devre dışı bırak
                }
            ]
        });

        // Saat seçimi
        document.querySelectorAll('.saat-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.classList.contains('musait')) {
                    // Tüm seçimleri kaldır
                    document.querySelectorAll('.saat-btn').forEach(b => {
                        b.classList.remove('secili');
                    });
                    
                    // Bu butonu seçili yap
                    this.classList.add('secili');
                    
                    // Saat bilgilerini form'a ekle
                    const baslangic = this.dataset.saat + ':00';
                    const bitis = new Date('1970-01-01T' + baslangic);
                    bitis.setMinutes(bitis.getMinutes() + 30);
                    const bitisStr = bitis.toTimeString().substring(0, 8);
                    
                    document.getElementById('seciliSaat').value = baslangic;
                    document.getElementById('bitisSaati').value = bitisStr;
                }
            });
        });

        // Form gönderim kontrolü
        document.getElementById('randevuOnayForm')?.addEventListener('submit', function(e) {
            if (!document.getElementById('seciliSaat')?.value) {
                e.preventDefault();
                alert('Lütfen uygun bir saat seçiniz!');
            }
        });
    </script>
</body>
</html>