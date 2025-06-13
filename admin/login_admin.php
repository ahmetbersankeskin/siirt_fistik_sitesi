<?php
// login_admin.php dosyası "admin" klasöründe.
// db_baglanti.php ve fonksiyonlar.php ise bir üst dizindeki "includes" klasöründe.
require_once '../includes/db_baglanti.php';
require_once '../includes/fonksiyonlar.php'; // session_start() ve diğer fonksiyonlar için

// Eğer yönetici zaten giriş yapmışsa, admin ana sayfasına yönlendir
if (admin_giris_yapti_mi()) {
    yonlendir('index_admin.php');
}

$form_kullanici_adi_admin = "";
$hata_mesaji_admin_login = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eylem_admin_giris'])) {
    $form_kullanici_adi_admin = veri_temizle($_POST['kullanici_adi_admin_form']);
    $sifre_admin_form = $_POST['sifre_admin_form'];

    if (empty($form_kullanici_adi_admin) || empty($sifre_admin_form)) {
        $hata_mesaji_admin_login = "Kullanıcı adı ve şifre alanları boş bırakılamaz.";
    } else {
        if (isset($pdo)) {
            try {
                $stmt_yonetici_bul = $pdo->prepare("SELECT yonetici_id, kullanici_adi, sifre FROM YoneticiKullanicilar WHERE kullanici_adi = ?");
                $stmt_yonetici_bul->execute([$form_kullanici_adi_admin]);
                $yonetici_db = $stmt_yonetici_bul->fetch();

                if ($yonetici_db && sifre_dogrula($sifre_admin_form, $yonetici_db['sifre'])) {
                    // Giriş başarılı, session değişkenlerini ayarla
                    $_SESSION['yonetici_id_session'] = $yonetici_db['yonetici_id'];
                    $_SESSION['yonetici_kullanici_adi_session'] = $yonetici_db['kullanici_adi'];

                    yonlendir('index_admin.php'); // Admin ana sayfasına yönlendir
                } else {
                    $hata_mesaji_admin_login = "Kullanıcı adı veya şifre hatalı.";
                }
            } catch (PDOException $e) {
                error_log("Admin giriş hatası (login_admin.php): " . $e->getMessage());
                $hata_mesaji_admin_login = "Giriş işlemi sırasında bir veritabanı sorunu oluştu.";
            }
        } else {
            $hata_mesaji_admin_login = "Veritabanı bağlantısı kurulamadı.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli Girişi - Siirt Keskin Fıstık</title>
    <!-- Ana CSS dosyamızı kullanabiliriz veya admin için özel bir CSS oluşturabiliriz -->
    <!-- Yolun ../assets/css/style.css olduğuna dikkat edin (admin klasöründen bir üst dizine çıkıyoruz) -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Admin login sayfasına özel ek stiller */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #e9ecef; /* Biraz daha farklı bir arka plan */
        }
        .admin-login-container {
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px; /* Maksimum genişlik */
        }
        .admin-login-container h1 {
            text-align: center;
            margin-bottom: 25px;
            color: #2c5e2e; /* Tema yeşili */
            font-size: 1.8em;
        }
        .admin-login-container form div {
            margin-bottom: 15px;
        }
        .admin-login-container label {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .admin-login-container input[type="text"],
        .admin-login-container input[type="password"] {
            font-size: 1em; /* Form elemanlarının font boyutu */
        }
        .admin-login-container button[type="submit"] {
            width: 100%; /* Butonu tam genişlik yap */
            padding: 12px;
            font-size: 1.1em;
        }
        .back-to-site {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <h1>Yönetici Girişi</h1>

        <?php if (!empty($hata_mesaji_admin_login)): ?>
            <p class="message error"><?php echo htmlspecialchars($hata_mesaji_admin_login); ?></p>
        <?php endif; ?>

        <form action="login_admin.php" method="post" novalidate>
            <div>
                <label for="kullanici_adi_admin_form_id">Kullanıcı Adı:</label>
                <input type="text" id="kullanici_adi_admin_form_id" name="kullanici_adi_admin_form" value="<?php echo htmlspecialchars($form_kullanici_adi_admin); ?>" required autofocus>
            </div>
            <div>
                <label for="sifre_admin_form_id">Şifre:</label>
                <input type="password" id="sifre_admin_form_id" name="sifre_admin_form" required>
            </div>
            <button type="submit" name="eylem_admin_giris">Giriş Yap</button>
        </form>
        <a href="../index.php" class="back-to-site" title="Ana Sayfaya Dön">← Siteye Geri Dön</a>
    </div>
</body>
</html>