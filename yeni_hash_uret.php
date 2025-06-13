<?php
// Bu dosya ana dizinde olduğu için, includes klasöründeki fonksiyonlar.php'yi
// 'includes/fonksiyonlar.php' şeklinde çağırıyoruz.
require_once 'includes/fonksiyonlar.php'; // sifre_hashle() fonksiyonu için

$istenen_sifre = 'admin123'; // Admin panelinde kullanmak istediğiniz şifre
$yeni_hash = sifre_hashle($istenen_sifre);

echo "Girmek istediğiniz şifre: <strong>" . htmlspecialchars($istenen_sifre) . "</strong><br><br>";
echo "Bu şifre için üretilen YENİ HASH (Bunu kopyalayın):<br>";
echo "<textarea rows='3' cols='70' readonly onclick='this.select()'>" . htmlspecialchars($yeni_hash) . "</textarea>";
echo "<br><br><strong>TALİMAT:</strong> Yukarıdaki textarea içindeki hash değerinin tamamını seçip kopyalayın. Ardından phpMyAdmin'e gidin, `YoneticiKullanicilar` tablosunda `kullanici_adi`' `admin` olan satırı bulun, 'Düzenle'ye tıklayın ve `sifre` alanındaki eski hash'i silip bu yeni kopyaladığınız hash'i oraya yapıştırın ve kaydedin.";
?>