<?php
require_once '../config.php';
yetki_kontrol(['musteri']);

// Müşterinin tüm randevularını getir
$randevular = $db->prepare("CALL musteri_randevulari(?)");
$randevular->execute([$_SESSION['kullanici']['kullanici_id']]);
$randevular = $randevular->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli - Berber Randevu Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .saat-secimi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0; }
        .saat-btn { padding: 10px; text-align: center; border-radius: 5px; position: relative; }
        .saat-btn.musait { background-color: #d4edda; border: 1px solid #c3e6cb; }
        .saat-btn.dolu { background-color: #f8d7da; border: 1px solid #f5c6cb; cursor: not-allowed; }
        .saat-btn.kapali { background-color: #e2e3e5; border: 1px solid #d6d8db; cursor: not-allowed; }
        .saat-btn.secili { background-color: #007bff; color: white; border-color: #007bff; }
        .saat-aciklama { font-size: 11px; color: #666; margin-top: 5px; }
        .randevu-karti { margin-bottom: 10px; }
        .beklemede { border-left: 4px solid #ffc107; }
        .onaylandi { border-left: 4px solid #28a745; }
        .reddedildi { border-left: 4px solid #dc3545; }
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
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Randevularım</h5>
            </div>
            <div class="card-body">
                <?php if (empty($randevular)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Henüz randevunuz bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
							<thead>
								<tr>
									<th>Berber</th>
									<th>Tarih</th>
									<th>Saat</th>
									<th>Durum</th>
									<th>İşlem</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($randevular as $randevu): ?>
									<tr class="<?php echo strtolower(str_replace(' ', '', $randevu['durum'])); ?>">
										<td><?php echo htmlspecialchars($randevu['personel_ad'].' '.$randevu['personel_soyad']); ?></td>
										<td><?php echo date('d.m.Y', strtotime($randevu['tarih'])); ?></td>
										<td><?php echo date('H:i', strtotime($randevu['baslangic_saati'])).' - '.date('H:i', strtotime($randevu['bitis_saati'])); ?></td>
										<td>
											<span class="badge bg-<?php 
												echo ($randevu['durum'] == 'Beklemede') ? 'warning text-dark' : 
													(($randevu['durum'] == 'Onaylandı') ? 'success' : 
													(($randevu['durum'] == 'İptal Edildi') ? 'danger' : 
													'primary'));
											?>">
												<?php echo htmlspecialchars($randevu['durum']); ?>
											</span>
										</td>
										<td>
											<?php if ($randevu['durum_id'] == 1 || $randevu['durum_id'] == 2): ?>
												<form method="POST" action="randevu_iptal.php" onsubmit="return confirm('Bu randevuyu iptal etmek istediğinize emin misiniz?');">
													<input type="hidden" name="randevu_id" value="<?php echo $randevu['randevu_id']; ?>">
													<button type="submit" name="randevu_iptal" class="btn btn-sm btn-danger">
														<i class="bi bi-x-circle"></i> İptal Et
													</button>
												</form>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/tr.js"></script>
    <script>
        // Tarih seçici ayarı (Türkçe)
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

        // Saat seçimi işlemi
        document.querySelectorAll('.saat-btn').forEach(btn => {
			btn.addEventListener('click', function () {
				if (this.classList.contains('musait')) {
					document.querySelectorAll('.saat-btn').forEach(b => b.classList.remove('secili'));
					this.classList.add('secili');
					document.getElementById('seciliSaat').value = this.dataset.saat;
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