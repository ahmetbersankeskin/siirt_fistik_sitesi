<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";

$girilen_sifre_test = 'admin123';

$veritabanindaki_hash_test = '$2y$10$BeP.BcxS.Ja8UjRz09Vj0uL.70N9WvK.P0Y9xP3FzGk8L6xQcE9B6';

echo "Test Edilen Düz Metin Şifre: " . htmlspecialchars($girilen_sifre_test) . "\n";
echo "Veritabanındaki Hash: " . htmlspecialchars($veritabanindaki_hash_test) . "\n\n";

if (password_verify($girilen_sifre_test, $veritabanindaki_hash_test)) {
    echo "<strong style='color:green;'>PASSWORD_VERIFY SONUCU: DOĞRU!</strong>\n";
    echo "Bu, 'admin123' şifresinin veritabanınızdaki hash ile eşleştiği anlamına gelir.\n";
    echo "Eğer admin login sayfasında hala sorun yaşıyorsanız, sorun muhtemelen:\n";
    echo "1. Login formunda kullanıcı adı veya şifreyi yanlış yazmanız.\n";
    echo "2. login_admin.php dosyasındaki SQL sorgusunun kullanıcıyı bulamaması (kullanıcı adı farklı olabilir).\n";
    echo "3. Session başlatma veya ayarlama ile ilgili bir sorun.\n";
} else {
    echo "<strong style='color:red;'>PASSWORD_VERIFY SONUCU: YANLIŞ!</strong>\n";
    echo "Bu, 'admin123' şifresinin veritabanınızdaki hash ile EŞLEŞMEDİĞİ anlamına gelir.\n";
    echo "Olası Nedenler:\n";
    echo "1. Veritabanındaki hash gerçekten 'admin123' şifresine ait değil.\n";
    echo "2. Hash kopyalanırken bir hata yapılmış olabilir.\n";
    echo "3. PHP sürümünüz veya Bcrypt kütüphanesi ile ilgili çok nadir bir uyumsuzluk olabilir (çok düşük ihtimal).\n\n";

    echo "'admin123' şifresi için YENİ BİR HASH OLUŞTURULUYOR:\n";
    $yeni_olusturulan_hash = password_hash($girilen_sifre_test, PASSWORD_DEFAULT);
    echo htmlspecialchars($yeni_olusturulan_hash) . "\n";
    echo "Eğer yukarıdaki test 'YANLIŞ' sonucu verdiyse, bu YENİ OLUŞTURULAN HASH'i kopyalayıp phpMyAdmin üzerinden\n";
    echo "`YoneticiKullanicilar` tablosundaki `admin` kullanıcısının `sifre` alanına yapıştırmayı deneyin.\n";
    echo "Ardından admin login sayfasında 'admin' ve 'admin123' ile tekrar giriş yapmayı deneyin.\n";
}

echo "\nPHP Sürümü: " . phpversion() . "\n";
if (defined('PASSWORD_BCRYPT')) {
    echo "PASSWORD_BCRYPT tanımlı.\n";
} else {
    echo "PASSWORD_BCRYPT tanımlı DEĞİL (Bu bir sorun olabilir!).\n";
}

echo "</pre>";
?>