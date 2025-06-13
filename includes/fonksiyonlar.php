<?php
// Oturum yönetimi her zaman en üstte olmalı
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Müşteri giriş yapmış mı kontrolü
function musteri_giris_yapti_mi() {
    return isset($_SESSION['musteri_id_session']);
}

// Giriş yapmış müşterinin ID'sini getirir
function get_musteri_id() {
    return $_SESSION['musteri_id_session'] ?? null; // Eğer session yoksa null döndür
}

// Yönetici giriş yapmış mı kontrolü
function admin_giris_yapti_mi() {
    return isset($_SESSION['yonetici_id_session']);
}

// Belirtilen URL'ye yönlendirme yapar
function yonlendir($url) {
    // Eğer daha önce herhangi bir çıktı gönderilmediyse header ile yönlendir
    if (!headers_sent()) {
        header("Location: " . $url);
        exit(); // Yönlendirmeden sonra script'in çalışmasını durdur
    }
    // Eğer header zaten gönderilmişse (bir hata veya çıktı nedeniyle), JavaScript ile yönlendirmeyi dene
    else {
        echo "<script type='text/javascript'>window.location.href='$url';</script>";
        // JavaScript'in kapalı olma ihtimaline karşı meta refresh
        echo "<noscript><meta http-equiv='refresh' content='0;url=$url'></noscript>";
        exit(); // Script'i sonlandır
    }
}

// Kullanıcıdan gelen veriyi temizler (Basit XSS önlemi ve trim)
function veri_temizle($veri) {
    $veri = trim($veri); // Başındaki ve sonundaki boşlukları kaldır
    $veri = stripslashes($veri); // Ters eğik çizgileri kaldır
    $veri = htmlspecialchars($veri, ENT_QUOTES, 'UTF-8'); // Özel HTML karakterlerini dönüştür
    return $veri;
}

// Şifreyi güvenli bir şekilde hash'ler
function sifre_hashle($sifre) {
    return password_hash($sifre, PASSWORD_DEFAULT);
}

// Girilen şifre ile hash'lenmiş şifreyi doğrular
function sifre_dogrula($sifre, $hashlenmis_sifre) {
    return password_verify($sifre, $hashlenmis_sifre);
}

// Sepetteki toplam ürün (adet) sayısını verir
function sepetteki_urun_sayisi() {
    $toplam_adet = 0;
    if (isset($_SESSION['sepet_session']) && is_array($_SESSION['sepet_session'])) {
        foreach ($_SESSION['sepet_session'] as $urun_id => $urun_bilgisi) {
            if (isset($urun_bilgisi['miktar']) && is_numeric($urun_bilgisi['miktar'])) {
                $toplam_adet += intval($urun_bilgisi['miktar']);
            }
        }
    }
    return $toplam_adet;
}

// Sepetin toplam tutarını hesaplar
// Bu fonksiyonun çalışması için $pdo (veritabanı bağlantısı) gereklidir.
// $pdo parametre olarak fonksiyona gönderilmeli.
function sepet_toplam_tutar($pdo_baglantisi) {
    $toplam_fiyat = 0.0;
    if (isset($_SESSION['sepet_session']) && !empty($_SESSION['sepet_session']) && is_array($_SESSION['sepet_session'])) {
        $urun_idler = array_keys($_SESSION['sepet_session']);

        if (empty($urun_idler)) {
            return $toplam_fiyat;
        }

        // SQL sorgusu için placeholder'lar oluşturma (?,?,?)
        $placeholders = implode(',', array_fill(0, count($urun_idler), '?'));

        try {
            $stmt = $pdo_baglantisi->prepare("SELECT urun_id, fiyat FROM Urunler WHERE urun_id IN ($placeholders)");
            $stmt->execute($urun_idler);
            $urun_fiyatlari_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // urun_id => fiyat şeklinde getir

            foreach ($_SESSION['sepet_session'] as $urun_id => $urun_bilgisi) {
                if (isset($urun_fiyatlari_db[$urun_id]) && isset($urun_bilgisi['miktar'])) {
                    $toplam_fiyat += $urun_fiyatlari_db[$urun_id] * $urun_bilgisi['miktar'];
                }
            }
        } catch (PDOException $e) {
            error_log("Sepet toplam tutar hesaplama hatası: " . $e->getMessage());
            // Hata durumunda 0 döndür veya bir hata mesajı ayarla
            return 0.0;
        }
    }
    return $toplam_fiyat;
}

// Kullanıcıya gösterilecek geçici (flash) mesajları ayarlar
function mesaj_ayarla($mesaj_anahtari, $mesaj_icerigi, $mesaj_tipi = 'success') {
    $_SESSION['flash_mesajlar_session'][$mesaj_anahtari] = [
        'mesaj' => $mesaj_icerigi,
        'tip' => $mesaj_tipi // 'success', 'error', 'info', 'warning' olabilir
    ];
}

// Ayarlanmış flash mesajları gösterir ve session'dan siler
function mesaj_goster($mesaj_anahtari) {
    if (isset($_SESSION['flash_mesajlar_session'][$mesaj_anahtari])) {
        $mesaj_detayi = $_SESSION['flash_mesajlar_session'][$mesaj_anahtari];
        echo '<div class="message ' . htmlspecialchars($mesaj_detayi['tip']) . '">' . htmlspecialchars($mesaj_detayi['mesaj']) . '</div>';
        // Mesaj gösterildikten sonra session'dan kaldır
        unset($_SESSION['flash_mesajlar_session'][$mesaj_anahtari]);
    }
}

?>