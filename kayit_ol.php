<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

// Eğer kullanıcı zaten giriş yapmışsa, ana sayfaya yönlendir
if (musteri_giris_yapti_mi()) {
    yonlendir('index.php');
}

// Formdan gelen veriler ve hata mesajları için değişkenler
$form_ad = "";
$form_soyad = "";
$form_email = "";
$form_telefon = ""; // Opsiyonel
$form_adres = "";   // Opsiyonel
// Şifre alanları güvenlik nedeniyle tekrar doldurulmaz, sadece hata mesajları gösterilir.

$hatalar = []; // Hata mesajlarını tutacak dizi

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eylem_kayit_ol'])) {
    // Form verilerini al ve temizle
    $form_ad = veri_temizle($_POST['ad_form']);
    $form_soyad = veri_temizle($_POST['soyad_form']);
    $form_email = veri_temizle($_POST['email_form']);
    $form_telefon = isset($_POST['telefon_form']) ? veri_temizle($_POST['telefon_form']) : null;
    $form_adres = isset($_POST['adres_form']) ? veri_temizle($_POST['adres_form']) : null;
    $sifre_form = $_POST['sifre_form']; // Şifre temizlenmez, direkt hash'lenir
    $sifre_tekrar_form = $_POST['sifre_tekrar_form'];

    // Doğrulama (Validation)
    if (empty($form_ad)) {
        $hatalar['ad'] = "Ad alanı boş bırakılamaz.";
    }
    if (empty($form_soyad)) {
        $hatalar['soyad'] = "Soyad alanı boş bırakılamaz.";
    }
    if (empty($form_email)) {
        $hatalar['email'] = "E-posta alanı boş bırakılamaz.";
    } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
        $hatalar['email'] = "Lütfen geçerli bir e-posta adresi giriniz.";
    } else {
        // E-posta adresinin veritabanında daha önce kullanılıp kullanılmadığını kontrol et
        if (isset($pdo)) {
            try {
                $stmt_email_kontrol = $pdo->prepare("SELECT musteri_id FROM Musteriler WHERE email = ?");
                $stmt_email_kontrol->execute([$form_email]);
                if ($stmt_email_kontrol->fetch()) {
                    $hatalar['email'] = "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta deneyin veya giriş yapın.";
                }
            } catch (PDOException $e) {
                error_log("E-posta kontrol hatası (kayit_ol.php): " . $e->getMessage());
                $hatalar['veritabani'] = "Kayıt işlemi sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
            }
        }
    }

    if (empty($sifre_form)) {
        $hatalar['sifre'] = "Şifre alanı boş bırakılamaz.";
    } elseif (strlen($sifre_form) < 6) {
        $hatalar['sifre'] = "Şifreniz en az 6 karakter uzunluğunda olmalıdır.";
    }

    if (empty($sifre_tekrar_form)) {
        $hatalar['sifre_tekrar'] = "Şifre tekrar alanı boş bırakılamaz.";
    } elseif ($sifre_form !== $sifre_tekrar_form) {
        $hatalar['sifre_tekrar'] = "Girdiğiniz şifreler uyuşmuyor.";
    }

    // Eğer hiç hata yoksa, veritabanına kaydı yap
    if (empty($hatalar) && isset($pdo)) {
        $hashlenmis_sifre = sifre_hashle($sifre_form);
        try {
            $stmt_kayit = $pdo->prepare(
                "INSERT INTO Musteriler (ad, soyad, email, sifre, telefon, adres, kayit_tarihi)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())" // kayit_tarihi otomatik eklenecek
            );
            $stmt_kayit->execute([$form_ad, $form_soyad, $form_email, $hashlenmis_sifre, $form_telefon, $form_adres]);

            mesaj_ayarla('global_mesaj', 'Kaydınız başarıyla tamamlandı! Şimdi giriş yapabilirsiniz.', 'success');
            yonlendir('giris_yap.php'); // Kayıt başarılıysa giriş sayfasına yönlendir

        } catch (PDOException $e) {
            error_log("Müşteri kayıt hatası (kayit_ol.php): " . $e->getMessage());
            $hatalar['veritabani'] = "Kayıt işlemi sırasında beklenmedik bir veritabanı sorunu oluştu.";
        }
    } elseif (empty($hatalar) && !isset($pdo)) {
        $hatalar['veritabani'] = "Veritabanı bağlantısı kurulamadığı için kayıt yapılamıyor.";
    }
}
?>

<div class="ana-container sayfa-icerik">
    <h1>Yeni Müşteri Kaydı</h1>

    <?php if (!empty($hatalar['veritabani'])): ?>
        <p class="message error"><?php echo htmlspecialchars($hatalar['veritabani']); ?></p>
    <?php endif; ?>

    <form action="kayit_ol.php" method="post" novalidate>
        <div>
            <label for="ad_form_id">Adınız <span style="color:red;">*</span></label>
            <input type="text" id="ad_form_id" name="ad_form" value="<?php echo htmlspecialchars($form_ad); ?>" required>
            <?php if (isset($hatalar['ad'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar['ad']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="soyad_form_id">Soyadınız <span style="color:red;">*</span></label>
            <input type="text" id="soyad_form_id" name="soyad_form" value="<?php echo htmlspecialchars($form_soyad); ?>" required>
            <?php if (isset($hatalar['soyad'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar['soyad']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="email_form_id">E-posta Adresiniz <span style="color:red;">*</span></label>
            <input type="email" id="email_form_id" name="email_form" value="<?php echo htmlspecialchars($form_email); ?>" required>
            <?php if (isset($hatalar['email'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar['email']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="sifre_form_id">Şifreniz <span style="color:red;">*</span> (En az 6 karakter)</label>
            <input type="password" id="sifre_form_id" name="sifre_form" required>
            <?php if (isset($hatalar['sifre'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar['sifre']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="sifre_tekrar_form_id">Şifreniz (Tekrar) <span style="color:red;">*</span></label>
            <input type="password" id="sifre_tekrar_form_id" name="sifre_tekrar_form" required>
            <?php if (isset($hatalar['sifre_tekrar'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar['sifre_tekrar']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="telefon_form_id">Telefon Numaranız (Opsiyonel)</label>
            <input type="tel" id="telefon_form_id" name="telefon_form" value="<?php echo htmlspecialchars($form_telefon); ?>" placeholder="Örn: 5551234567">
            <?php if (isset($hatalar['telefon'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar['telefon']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="adres_form_id">Adresiniz (Opsiyonel)</label>
            <textarea id="adres_form_id" name="adres_form" rows="4" placeholder="Teslimat için kullanılacak adresiniz..."><?php echo htmlspecialchars($form_adres); ?></textarea>
            <?php if (isset($hatalar['adres'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar['adres']; ?></small><?php endif; ?>
        </div>

        <button type="submit" name="eylem_kayit_ol">Kayıt Ol</button>
    </form>

    <p style="margin-top: 20px; text-align: center;">
        Zaten bir hesabınız var mı? <a href="giris_yap.php">Giriş Yapın</a>
    </p>

</div>

<?php
require_once 'includes/footer_site.php';
?>