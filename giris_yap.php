<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

// Eğer kullanıcı zaten giriş yapmışsa, ana sayfaya veya hesabım sayfasına yönlendir
if (musteri_giris_yapti_mi()) {
    yonlendir('hesabim.php'); // Veya index.php
}

$form_email_giris = ""; // Formdan gelen e-posta için
$hata_mesaji_giris = "";  // Giriş hataları için

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eylem_giris_yap'])) {
    $form_email_giris = veri_temizle($_POST['email_giris_form']);
    $sifre_giris_form = $_POST['sifre_giris_form']; // Şifre temizlenmez

    if (empty($form_email_giris) || empty($sifre_giris_form)) {
        $hata_mesaji_giris = "E-posta ve şifre alanları boş bırakılamaz.";
    } else {
        if (isset($pdo)) {
            try {
                $stmt_musteri_bul = $pdo->prepare("SELECT musteri_id, email, sifre, ad FROM Musteriler WHERE email = ?");
                $stmt_musteri_bul->execute([$form_email_giris]);
                $musteri_db = $stmt_musteri_bul->fetch();

                if ($musteri_db && sifre_dogrula($sifre_giris_form, $musteri_db['sifre'])) {
                    // Giriş başarılı, session değişkenlerini ayarla
                    $_SESSION['musteri_id_session'] = $musteri_db['musteri_id'];
                    $_SESSION['musteri_email_session'] = $musteri_db['email'];
                    $_SESSION['musteri_ad_session'] = $musteri_db['ad']; // İsim de session'a alınabilir

                    mesaj_ayarla('global_mesaj', 'Hoş geldiniz, ' . htmlspecialchars($musteri_db['ad']) . '! Başarıyla giriş yaptınız.', 'success');

                    // Eğer ödeme veya başka bir özel sayfadan yönlendirme varsa oraya git
                    if (isset($_SESSION['yonlendirme_url_session'])) {
                        $yonlendirme_adresi = $_SESSION['yonlendirme_url_session'];
                        unset($_SESSION['yonlendirme_url_session']); // Session'ı temizle
                        yonlendir($yonlendirme_adresi);
                    } else {
                        yonlendir('index.php'); // Varsayılan olarak ana sayfaya yönlendir
                    }
                } else {
                    $hata_mesaji_giris = "Girdiğiniz e-posta veya şifre hatalı. Lütfen bilgilerinizi kontrol edin.";
                }
            } catch (PDOException $e) {
                error_log("Müşteri giriş hatası (giris_yap.php): " . $e->getMessage());
                $hata_mesaji_giris = "Giriş işlemi sırasında bir veritabanı sorunu oluştu. Lütfen daha sonra tekrar deneyin.";
            }
        } else {
            $hata_mesaji_giris = "Veritabanı bağlantısı kurulamadığı için giriş yapılamıyor.";
        }
    }
}
?>

<div class="ana-container sayfa-icerik">
    <h1>Müşteri Girişi</h1>

    <?php if (!empty($hata_mesaji_giris)): ?>
        <p class="message error"><?php echo htmlspecialchars($hata_mesaji_giris); ?></p>
    <?php endif; ?>

    <?php mesaj_goster('giris_yap_mesaj'); // Kayıt sonrası veya başka yönlendirmelerden gelen mesajlar ?>

    <form action="giris_yap.php" method="post" novalidate>
        <div>
            <label for="email_giris_form_id">E-posta Adresiniz:</label>
            <input type="email" id="email_giris_form_id" name="email_giris_form" value="<?php echo htmlspecialchars($form_email_giris); ?>" required>
        </div>

        <div>
            <label for="sifre_giris_form_id">Şifreniz:</label>
            <input type="password" id="sifre_giris_form_id" name="sifre_giris_form" required>
        </div>

        <button type="submit" name="eylem_giris_yap">Giriş Yap</button>
    </form>

    <p style="margin-top: 20px; text-align: center;">
        Hesabınız yok mu? <a href="kayit_ol.php">Yeni Hesap Oluşturun</a>
    </p>
    <!-- <p style="margin-top: 10px; text-align: center;">
        <a href="sifremi_unuttum.php">Şifremi Unuttum</a>
    </p> -->

</div>

<?php
require_once 'includes/footer_site.php';
?>