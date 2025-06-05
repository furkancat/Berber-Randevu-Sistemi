CREATE DATABASE berber_randevu;
USE berber_randevu;

CREATE TABLE kullanicilar (
    kullanici_id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(50) NOT NULL,
    soyad VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL CHECK (email LIKE '%@%.%'),
    sifre VARCHAR(255) NOT NULL,
    telefon VARCHAR(15),
    rol VARCHAR(50) NOT NULL,
    kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE randevu_durumlari (
    randevu_durum_id INT AUTO_INCREMENT PRIMARY KEY,
    durum VARCHAR(50) NOT NULL
);

CREATE TABLE randevular (
    id INT AUTO_INCREMENT PRIMARY KEY,
    musteri_id INT NOT NULL,
    personel_id INT NOT NULL,
    sure INT NOT NULL,
    tarih DATE NOT NULL,
    baslangic_saati TIME NOT NULL,
    bitis_saati TIME NOT NULL,
    durum_id INT NOT NULL DEFAULT 1,
    olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (musteri_id) REFERENCES kullanicilar(kullanici_id),
    FOREIGN KEY (personel_id) REFERENCES kullanicilar(kullanici_id),
    FOREIGN KEY (durum_id) REFERENCES randevu_durumlari(randevu_durum_id)
);

CREATE TABLE kapali_saatler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    tarih DATE NOT NULL,
    baslangic_saati TIME NOT NULL,
    bitis_saati TIME NOT NULL,
    aciklama VARCHAR(255),
    FOREIGN KEY (personel_id) REFERENCES kullanicilar(kullanici_id)
);

DELIMITER //
CREATE PROCEDURE dolu_saatleri_getir(
    IN p_personel_id INT,
    IN p_tarih DATE
)
BEGIN
    SELECT TIME_FORMAT(baslangic_saati, '%H:%i') AS baslangic
    FROM randevular 
    WHERE personel_id = p_personel_id 
    AND tarih = p_tarih
    AND durum_id IN (1, 2);
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kapali_saat_ekle(
    IN p_personel_id INT,
    IN p_tarih DATE,
    IN p_baslangic_saati TIME,
    IN p_bitis_saati TIME,
    IN p_aciklama VARCHAR(255)
)
BEGIN
    INSERT INTO kapali_saatler (personel_id, tarih, baslangic_saati, bitis_saati, aciklama)
    VALUES (p_personel_id, p_tarih, p_baslangic_saati, p_bitis_saati, p_aciklama);
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kapali_saat_guncelle(
    IN p_kapali_saat_id INT,
    IN p_personel_id INT,
    IN p_yeni_tarih DATE,
    IN p_yeni_baslangic TIME,
    IN p_yeni_bitis TIME,
    IN p_yeni_aciklama VARCHAR(255),
    OUT p_sonuc VARCHAR(255))
BEGIN
    DECLARE etkilenen_satir INT;
    DECLARE cakisma_var INT;
    
    START TRANSACTION;
    
    -- Çakışma kontrolü (kendisi hariç diğer kapalı saatlerle)
    SELECT COUNT(*) INTO cakisma_var
    FROM kapali_saatler
    WHERE personel_id = p_personel_id
    AND tarih = p_yeni_tarih
    AND kapali_saat_id != p_kapali_saat_id
    AND (
        (baslangic_saati < p_yeni_bitis AND bitis_saati > p_yeni_baslangic)
    );
    
    IF cakisma_var > 0 THEN
        ROLLBACK;
        SET p_sonuc = 'Bu saat aralığında zaten kapalı saat tanımlı';
    ELSE
        -- Güncelleme işlemi
        UPDATE kapali_saatler
        SET 
            tarih = p_yeni_tarih,
            baslangic_saati = p_yeni_baslangic,
            bitis_saati = p_yeni_bitis,
            aciklama = p_yeni_aciklama
        WHERE 
            kapali_saat_id = p_kapali_saat_id AND 
            personel_id = p_personel_id;
        
        SET etkilenen_satir = ROW_COUNT();
        
        IF etkilenen_satir = 0 THEN
            ROLLBACK;
            SET p_sonuc = 'Kapalı saat bulunamadı veya güncelleme yetkiniz yok';
        ELSE
            COMMIT;
            SET p_sonuc = 'Kapalı saat başarıyla güncellendi';
        END IF;
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kapali_saat_listesi(IN p_id INT)
BEGIN
	SELECT * FROM kapali_saatler WHERE personel_id = p_id AND tarih >= CURDATE()
	ORDER BY tarih, baslangic_saati;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kapali_saat_sil(
    IN p_kapali_saat_id INT,
    IN p_personel_id INT,
    OUT p_sonuc VARCHAR(255)
    )
BEGIN
    DECLARE etkilenen_satir INT;
    
    START TRANSACTION;
    
    -- Kapalı saati sil (sadece ilgili personel silebilsin diye personel_id kontrolü)
    DELETE FROM kapali_saatler 
    WHERE kapali_saat_id = p_kapali_saat_id AND personel_id = p_personel_id;
    
    SET etkilenen_satir = ROW_COUNT();
    
    IF etkilenen_satir = 0 THEN
        ROLLBACK;
        SET p_sonuc = 'Kapalı saat bulunamadı veya silme yetkiniz yok';
    ELSE
        COMMIT;
        SET p_sonuc = 'Kapalı saat başarıyla silindi';
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kapali_saatleri_getir(
    IN p_personel_id INT,
    IN p_tarih DATE
)
BEGIN
    SELECT 
        TIME_FORMAT(baslangic_saati, '%H:%i') AS baslangic,
        TIME_FORMAT(bitis_saati, '%H:%i') AS bitis,
        aciklama 
    FROM kapali_saatler 
    WHERE personel_id = p_personel_id 
    AND tarih = p_tarih;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kullanici_adsoyad_al(p_id INT)
BEGIN
	SELECT ad, soyad FROM kullanicilar where kullanici_id = p_id;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kullanici_al(p_id INT)
BEGIN
	SELECT * FROM kullanicilar where kullanici_id = p_id;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE kullanici_ekle(
    IN p_ad VARCHAR(50),
    IN p_soyad VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_sifre VARCHAR(255),
    IN p_telefon VARCHAR(15),
    IN p_rol VARCHAR(50),
    OUT p_id INT
)
BEGIN
    INSERT INTO kullanicilar (ad, soyad, email, sifre, telefon, rol)
    VALUES (p_ad, p_soyad, p_email, p_sifre, p_telefon, p_rol);
    SET p_id = LAST_INSERT_ID();
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE kullanici_email_ile_al(IN p_email VARCHAR(100))
BEGIN
	SELECT * FROM kullanicilar WHERE email = p_email;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE kullanici_guncelle(
    IN p_id INT,
    IN p_ad VARCHAR(50),
    IN p_soyad VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_telefon VARCHAR(15),
    OUT p_sonuc VARCHAR(255)
)
BEGIN
    DECLARE v_email INT;
    
    -- Email kontrolü (başka bir kullanıcıda var mı)
    SELECT COUNT(*) INTO v_email 
    FROM kullanicilar 
    WHERE email = p_email AND kullanici_id != p_id;
    
    IF v_email > 0 THEN
        SET p_sonuc = 'Bu email adresi zaten kullanılıyor';
    ELSE
        UPDATE kullanicilar SET
            ad = p_ad,
            soyad = p_soyad,
            email = p_email,
            telefon = p_telefon
        WHERE kullanici_id = p_id;
        
        SET p_sonuc = 'Profil bilgileri başarıyla güncellendi';
    END IF;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE musteri_randevulari(IN p_id INT)
BEGIN
	SELECT r.*, k.ad AS personel_ad, k.soyad AS personel_soyad, rd.durum 
    FROM randevular AS r
    JOIN kullanicilar AS k ON r.personel_id = k.kullanici_id
    JOIN randevu_durumlari AS rd ON r.durum_id = rd.randevu_durum_id
    WHERE r.musteri_id = p_id 
    ORDER BY r.tarih DESC, r.baslangic_saati DESC;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE musteri_son5_randevu(IN p_id INT)
BEGIN
	SELECT r.*, p.ad AS personel_ad, p.soyad AS personel_soyad, rd.durum, rd.randevu_durum_id
    FROM randevular r
    JOIN kullanicilar p ON r.personel_id = p.kullanici_id
    JOIN randevu_durumlari rd ON r.durum_id = rd.randevu_durum_id
    WHERE r.musteri_id = p_id 
    ORDER BY r.tarih DESC, r.baslangic_saati DESC
    LIMIT 5;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE personel_ekle(
    IN p_ad VARCHAR(50),
    IN p_soyad VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_sifre VARCHAR(255),
    IN p_telefon VARCHAR(15),
    OUT p_sonuc VARCHAR(100)
)
BEGIN
    DECLARE v_email INT DEFAULT 0;
    
    START TRANSACTION;
    
    -- Email kontrolü
    SELECT COUNT(*) INTO v_email FROM kullanicilar WHERE email = p_email;
    IF v_email > 0 THEN
        ROLLBACK;
        SET p_sonuc = 'Bu email adresi zaten kullanılıyor';
    ELSE
        -- Kullanıcıyı ekle
        INSERT INTO kullanicilar (ad, soyad, email, sifre, telefon, rol)
        VALUES (p_ad, p_soyad, p_email, p_sifre, p_telefon, 'personel');
        
        COMMIT;
        SET p_sonuc = 'Personel başarıyla eklendi';
    END IF;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE personel_liste_admin_haric()
BEGIN
	SELECT * FROM kullanicilar WHERE rol = 'personel' ORDER BY ad ;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE personel_listele()
BEGIN
	SELECT kullanici_id, CONCAT(ad, ' ', soyad) AS ad_soyad FROM kullanicilar WHERE rol IN ('admin', 'personel');
END //
DELIMITER ;



DELIMITER //
CREATE PROCEDURE personel_randevulari(IN p_id INT)
BEGIN
	SELECT r.*, m.ad AS musteri_ad, m.soyad AS musteri_soyad, m.telefon, rd.durum
    FROM randevular AS r
    JOIN kullanicilar AS m ON r.musteri_id = m.kullanici_id
    JOIN randevu_durumlari AS rd ON r.durum_id = rd.randevu_durum_id
    WHERE r.personel_id = p_id AND r.tarih >= CURDATE()
    ORDER BY r.tarih ASC, r.baslangic_saati ASC;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE personel_sil(
    IN p_personel_id INT,
    OUT p_sonuc VARCHAR(255))
BEGIN
    DECLARE silinen_kayit INT;
    
    START TRANSACTION;
    
    -- Önce randevuları sil
    DELETE FROM randevular WHERE personel_id = p_personel_id;
    
    -- Kapalı saatleri sil
    DELETE FROM kapali_saatler WHERE personel_id = p_personel_id;
    
    -- Personeli sil
    DELETE FROM kullanicilar 
    WHERE kullanici_id = p_personel_id AND rol = 'personel';
    
    SET silinen_kayit = ROW_COUNT();
    
    IF silinen_kayit = 0 THEN
        ROLLBACK;
        SET p_sonuc = 'Personel bulunamadı veya silinemedi!';
    ELSE
        COMMIT;
        SET p_sonuc = 'Personel ve tüm ilişkili veriler başarıyla silindi!';
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE randevu_durum_guncelle(
    IN p_randevu_id INT,
    IN p_yeni_durum_id INT,
    OUT p_sonuc VARCHAR(255)
    )
BEGIN
    DECLARE mevcut_durum INT;
    
    -- Mevcut durumu al
    SELECT durum_id INTO mevcut_durum 
    FROM randevular 
    WHERE randevu_id = p_randevu_id;
    
    -- Durum kontrolü
    IF mevcut_durum IN (4,5) THEN
        SET p_sonuc = 'Tamamlanmış veya iptal edilmiş randevular güncellenemez!';
    ELSE
        -- Güncelleme işlemi
        UPDATE randevular 
        SET durum_id = p_yeni_durum_id 
        WHERE randevu_id = p_randevu_id;
        
        SET p_sonuc = 'Randevu durumu başarıyla güncellendi!';
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE randevu_durum_sil(p_durum_id INT)
BEGIN
	delete from randevu_durumlari where randevu_durum_id = p_durum_id;
END //
DELIMITER ;


DELIMITER //
CREATE PROCEDURE randevu_durumlari_al()
BEGIN
	SELECT * FROM randevu_durumlari;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE randevu_durumlari_ekle(p_durum VARCHAR(50))
BEGIN
	INSERT INTO randevu_durumlari (durum) VALUES 
	(p_durum);
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE randevu_ekle(
    IN p_musteri_id INT,
    IN p_personel_id INT,
    IN p_sure INT,
    IN p_tarih DATE,
    IN p_baslangic_saati TIME,
    OUT p_randevu_id INT
)
BEGIN
    DECLARE v_bitis_saati TIME;
    
    -- Bitiş saatini hesapla (başlangıç saati + süre)
    SET v_bitis_saati = ADDTIME(p_baslangic_saati, SEC_TO_TIME(p_sure * 60));
    
    INSERT INTO randevular (musteri_id, personel_id, sure, tarih, baslangic_saati, bitis_saati)
    VALUES (p_musteri_id, p_personel_id, p_sure, p_tarih, p_baslangic_saati, v_bitis_saati);
    
    SET p_randevu_id = LAST_INSERT_ID();
END //
DELIMITER ;



DELIMITER //
CREATE PROCEDURE randevu_iptal_et(
    IN p_randevu_id INT,
    IN p_musteri_id INT,
    OUT p_sonuc VARCHAR(100)
)
BEGIN
    DECLARE v_randevu_var INT DEFAULT 0;
    DECLARE v_durum INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_sonuc = CONCAT('Hata: ', @SQLSTATE, ' - ', SQLERRM);
    END;
    
    START TRANSACTION;
    
    -- Randevu var mı ve bu müşteriye mi ait kontrolü
    SELECT COUNT(*), durum_id INTO v_randevu_var, v_durum 
    FROM randevular 
    WHERE randevu_id = p_randevu_id AND musteri_id = p_musteri_id;
    
    IF v_randevu_var = 0 THEN
        ROLLBACK;
        SET p_sonuc = 'Randevu bulunamadı veya size ait değil';
    ELSEIF v_durum = 5 THEN
        ROLLBACK;
        SET p_sonuc = 'Bu randevu zaten iptal edilmiş';
    ELSEIF v_durum = 4 THEN
        ROLLBACK;
        SET p_sonuc = 'Tamamlanmış randevular iptal edilemez';
    ELSE
        -- Randevuyu iptal et (durum_id = 5 iptal durumu)
        UPDATE randevular SET durum_id = 5 WHERE randevu_id = p_randevu_id;
        
        -- İşlem başarılı
        COMMIT;
        SET p_sonuc = 'Randevu başarıyla iptal edildi';
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE randevu_olustur(
    IN p_musteri_id INT,
    IN p_personel_id INT,
    IN p_tarih DATE,
    IN p_baslangic_saati TIME,
    OUT p_sonuc VARCHAR(255)
)
BEGIN
    DECLARE v_bitis_saati TIME;
    DECLARE v_durum_id INT DEFAULT 1; -- Beklemede durumu
    
    -- Bitiş saatini hesapla (30 dakika sonra)
    SET v_bitis_saati = ADDTIME(p_baslangic_saati, '00:30:00');
    
    -- Çakışma kontrolü
    IF EXISTS (
        SELECT 1 FROM randevular 
        WHERE personel_id = p_personel_id 
        AND tarih = p_tarih 
        AND (
            (baslangic_saati < v_bitis_saati AND bitis_saati > p_baslangic_saati)
            AND durum_id IN (1, 2) -- Sadece beklemede ve onaylanmış randevuları kontrol et
        )
    ) THEN
        SET p_sonuc = 'Hata: Seçilen saatte başka bir randevu bulunmaktadır';
    ELSE
        -- Randevuyu oluştur
        INSERT INTO randevular (musteri_id, personel_id, tarih, baslangic_saati, bitis_saati, durum_id)
        VALUES (p_musteri_id, p_personel_id, p_tarih, p_baslangic_saati, v_bitis_saati, v_durum_id);
        
        SET p_sonuc = 'Randevu başarıyla oluşturuldu';
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE  PROCEDURE sifre_degistir(
    IN p_id INT,
    IN p_mevcut_sifre VARCHAR(255),
    IN p_yeni_sifre VARCHAR(255),
    OUT p_sonuc VARCHAR(255)
)
BEGIN
    DECLARE v_mevcut_hash VARCHAR(255);
    
    -- Mevcut hash'li şifreyi al
    SELECT sifre INTO v_mevcut_hash 
    FROM kullanicilar 
    WHERE kullanici_id = p_id;
    
    IF v_mevcut_hash IS NULL THEN
        SET p_sonuc = 'Kullanıcı bulunamadı';
    ELSEIF NOT EXISTS (
        SELECT 1 FROM kullanicilar 
        WHERE kullanici_id = p_id 
        AND sifre = v_mevcut_hash
        -- PHP tarafında password_verify kullanacağız, burada sadece hash karşılaştırması
    ) THEN
        SET p_sonuc = 'Mevcut şifre hatalı';
    ELSE
        -- Yeni şifreyi hash'leyerek güncelle
        UPDATE kullanicilar SET
            sifre = p_yeni_sifre -- PHP'de hash'lenmiş olarak gelecek
        WHERE kullanici_id = p_id;
        
        SET p_sonuc = 'Şifre başarıyla değiştirildi';
    END IF;
END //
DELIMITER ;


CALL randevu_durumlari_ekle('Beklemede');
CALL randevu_durumlari_ekle('Onaylandı');
CALL randevu_durumlari_ekle('Reddedildi');
CALL randevu_durumlari_ekle('Tamamlandı');
CALL randevu_durumlari_ekle('İptal Edildi');

DELIMITER //
CREATE FUNCTION uygun_randevu_saatleri(
    p_personel_id INT,
    p_tarih DATE,
    p_sure INT
) RETURNS TEXT
DETERMINISTIC
BEGIN
    DECLARE result TEXT DEFAULT '';
    DECLARE v_saat TIME;
    DECLARE v_bitis_saati TIME;
    DECLARE v_musait BOOLEAN;
    
    -- Çalışma saatleri (09:00 - 23:00)
    SET v_saat = '09:00:00';
    
    saat_dongusu: LOOP
        -- Bitiş saatini hesapla (30 dakika)
        SET v_bitis_saati = ADDTIME(v_saat, '00:30:00');
        
        -- Döngüden çıkış koşulu
        IF v_saat > '23:00:00' THEN
            LEAVE saat_dongusu;
        END IF;
        
        -- Çakışma kontrolü
        SET v_musait = NOT EXISTS (
            SELECT 1 FROM randevular 
            WHERE personel_id = p_personel_id 
            AND tarih = p_tarih 
            AND durum_id IN (1, 2) -- Beklemede veya Onaylandı
            AND (
                (baslangic_saati < v_bitis_saati AND bitis_saati > v_saat)
            )
        );
        
        -- Kapalı saat kontrolü
        IF v_musait THEN
            SET v_musait = NOT EXISTS (
                SELECT 1 FROM kapali_saatler 
                WHERE personel_id = p_personel_id 
                AND tarih = p_tarih 
                AND (
                    (baslangic_saati < v_bitis_saati AND bitis_saati > v_saat)
                )
            );
        END IF;
        
        -- Sonucu ekle
        IF v_musait THEN
            SET result = CONCAT(result, TIME_FORMAT(v_saat, '%H:%i'), '|');
        END IF;
        
        -- Saati 30 dakika ilerlet
        SET v_saat = ADDTIME(v_saat, '00:30:00');
    END LOOP;
    
    RETURN result;
END //
DELIMITER ; 

DELIMITER //
CREATE TRIGGER kapali_saat_kontrol_trigger
BEFORE INSERT ON kapali_saatler
FOR EACH ROW
BEGIN
    DECLARE kapali_saat_var INT;
    
    -- Aynı personel için aynı tarih ve çakışan saat aralığı kontrolü
    SELECT COUNT(*) INTO kapali_saat_var
    FROM kapali_saatler
    WHERE personel_id = NEW.personel_id
    AND tarih = NEW.tarih
    AND (
        (baslangic_saati < NEW.bitis_saati AND bitis_saati > NEW.baslangic_saati)
    );
    
    IF kapali_saat_var > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Bu personel için belirtilen tarih ve saat aralığı zaten kapalı olarak kayıtlıdır';
    END IF;
END //
DELIMITER ;


DELIMITER //
CREATE TRIGGER before_musteri_randevu_siniri
BEFORE INSERT ON randevular
FOR EACH ROW
BEGIN
    DECLARE aktif_randevu_sayisi INT;
    
    -- Müşterinin aktif randevularını say (Beklemede, Onaylandı veya Bugünden ileri tarihli Tamamlanmamış)
    SELECT COUNT(*) INTO aktif_randevu_sayisi 
    FROM randevular 
    WHERE musteri_id = NEW.musteri_id
    AND (
        durum_id IN (1, 2) -- Beklemede (1) veya Onaylandı (2)
        OR 
        (durum_id = 4 AND tarih >= CURDATE()) -- Tamamlanmış ama gelecekteki
    );
    
    -- Eğer 5 veya daha fazla aktif randevusu varsa hata ver
    IF aktif_randevu_sayisi >= 5 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Maksimum 5 aktif randevuya sahip olabilirsiniz. Yeni randevu oluşturmadan önce bazı randevularınızı iptal edin veya tamamlayın.';
    END IF;
END//
DELIMITER ;
