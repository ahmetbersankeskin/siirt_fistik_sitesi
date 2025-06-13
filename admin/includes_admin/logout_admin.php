<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre style='background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin:10px;'>";
echo "DEBUG: logout_admin.php BAŞLADI\n";

if (file_exists('../includes/fonksiyonlar.php')) {
    echo "   ../includes/fonksiyonlar.php dosyası bulundu.\n";
    require_once '../includes/fonksiyonlar.php';
    echo "   ../includes/fonksiyonlar.php YÜKLENDİ.\n";
    if (function_exists('yonlendir')) {
        echo "      yonlendir() fonksiyonu TANIMLI.\n";
    } else {
        echo "      <strong style='color:red;'>HATA: yonlendir() fonksiyonu TANIMLI DEĞİL!</strong>\n";
    }
} else {
    echo "   <strong style='color:red;'>KRİTİK HATA: ../includes/fonksiyonlar.php dosyası BULUNAMADI!</strong>\n";
    echo "</pre>";
    die("Gerekli dosya eksik, işlem durduruldu.");
}

echo "\nMevcut SESSION değişkenleri (Çıkış öncesi):\n";
var_dump($_SESSION);

echo "\n'yonetici_id_session' unset ediliyor...\n";
unset($_SESSION['yonetici_id_session']);
echo "'yonetici_kullanici_adi_session' unset ediliyor...\n";
unset($_SESSION['yonetici_kullanici_adi_session']);

echo "\nGüncel SESSION değişkenleri (Çıkış sonrası):\n";
var_dump($_SESSION);

echo "\n'login_admin.php' sayfasına yönlendirme deneniyor...\n";
// yonlendir('login_admin.php'); // ŞİMDİLİK YÖNLENDİRMEYİ KAPATALIM Kİ ÇIKTIYI GÖRELİM
echo "<a href='login_admin.php' style='font-size:1.2em; font-weight:bold; color:blue;'>Yönlendirme Test Linki (login_admin.php)</a>\n";
echo "\nDEBUG: logout_admin.php SONLANDI (Yönlendirme kapalıysa bu mesajı görmelisiniz)\n";
echo "</pre>";
exit(); // Script'in burada sonlandığından emin olalım
?>