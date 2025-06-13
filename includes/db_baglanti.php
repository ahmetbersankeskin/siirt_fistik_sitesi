<?php
// Veritabanı bağlantı ayarları
$host = 'localhost'; // Genellikle localhost
$dbname = 'siirt_fistik_db'; // Önceki adımlarda oluşturduğunuz veritabanının adı
$username = 'root';      // XAMPP için varsayılan kullanıcı adı
$password = 'abcd1234';          // XAMPP için varsayılan şifre (boş olabilir)

// Veritabanı bağlantısını kurma (PDO ile)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Hata modunu ayarlama (istisnaları fırlat)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Varsayılan fetch modunu ayarlama (ilişkisel dizi olarak getir)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Bağlantı başarılıysa test için (bu satırı daha sonra silebilirsiniz)
    // echo "Veritabanına başarıyla bağlanıldı!";

} catch (PDOException $e) {
    // Hata durumunda hatayı logla ve kullanıcıya genel bir mesaj göster
    error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
    // die() fonksiyonu script'i sonlandırır ve mesajı ekrana basar.
    // Canlı bir sitede bu kadar detaylı hata mesajı göstermek yerine daha genel bir mesaj tercih edilir.
    die("Üzgünüz, veritabanı bağlantısında bir sorun oluştu. Lütfen daha sonra tekrar deneyin. Hata Detayı: " . $e->getMessage());
}
?>