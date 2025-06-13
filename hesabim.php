<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

// Eğer kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
if (!musteri_giris_yapti_mi()) {
    mesaj_ayarla('global_mesaj', 'Hesabım sayfasını görüntüleyebilmek için lütfen giriş yapınız.', 'info');
    // Kullanıcıyı giriş yaptıktan sonra bu sayfaya geri yönlendirmek için session'a kaydedebiliriz
    $_SESSION['yonlendirme_url_session'] = 'hesabim.php';
    yonlendir('giris_yap.php');
}

$musteri_bilgileri = null;
$hata_mesaji_hesabim = "";

if (isset($pdo)) {
    $musteri_id = get_musteri_id(); // Giriş yapmış müşterinin ID'sini al
    if ($musteri_id) {
        try {
            $stmt_hesap = $pdo->prepare("SELECT ad, soyad, email, telefon, adres, kayit_tarihi FROM Musteriler WHERE musteri_id = ?");
            $stmt_hesap->execute([$musteri_id]);
            $musteri_bilgileri = $stmt_hesap->fetch();

            if (!$musteri_bilgileri) {
                // Bu durum normalde olmamalı (session'da ID var ama DB'de kullanıcı yoksa)
                error_log("Hesabım: Geçerli session ID'si ($musteri_id) için veritabanında kullanıcı bulunamadı.");
                $hata_mesaji_hesabim = "Hesap bilgileriniz yüklenirken bir sorun oluştu. Lütfen çıkış yapıp tekrar giriş yapmayı deneyin.";
                // Güvenlik önlemi olarak session'ı sonlandırıp giriş sayfasına yönlendirebiliriz
                // unset($_SESSION['musteri_id_session']); unset($_SESSION['musteri_email_session']); unset($_SESSION['musteri_ad_session']);
                // mesaj_ayarla('global_mesaj', $hata_mesaji_hesabim, 'error');
                // yonlendir('giris_yap.php');
            }
        } catch (PDOException $e) {
            error_log("Hesabım bilgi çekme hatası (hesabim.php): " . $e->getMessage());
            $hata_mesaji_hesabim = "Hesap bilgileriniz yüklenirken bir veritabanı sorunu oluştu.";
        }
    } else {
        // Bu da normalde olmamalı, !musteri_giris_yapti_mi() kontrolü bunu yakalamalı
        $hata_mesaji_hesabim = "Oturum bilgileri alınamadı.";
    }
} else {
    $hata_mesaji_hesabim = "Veritabanı bağlantısı kurulamadı.";
}

// İleride eklenecek: Bilgi Güncelleme Formu ve Şifre Değiştirme Formu için PHP işlemleri buraya gelebilir.
// Şimdilik sadece bilgileri gösteriyoruz.

?>

<div class="ana-container sayfa-icerik">
    <h1>Hesabım</h1>

    <?php if (!empty($hata_mesaji_hesabim)): ?>
        <p class="message error"><?php echo htmlspecialchars($hata_mesaji_hesabim); ?></p>
    <?php endif; ?>

    <?php mesaj_goster('hesabim_mesaj'); // Bilgi güncelleme vb. sonrası mesajlar için ?>

    <?php if ($musteri_bilgileri): ?>
        <h2>Kişisel Bilgileriniz</h2>
        <table class="cart-table" style="width:auto; min-width:400px;"> <!-- Basit bir tablo görünümü için cart-table stilini kullanabiliriz -->
            <tr>
                <th style="width:150px;">Adınız:</th>
                <td><?php echo htmlspecialchars($musteri_bilgileri['ad']); ?></td>
            </tr>
            <tr>
                <th>Soyadınız:</th>
                <td><?php echo htmlspecialchars($musteri_bilgileri['soyad']); ?></td>
            </tr>
            <tr>
                <th>E-posta Adresiniz:</th>
                <td><?php echo htmlspecialchars($musteri_bilgileri['email']); ?></td>
            </tr>
            <tr>
                <th>Telefon Numaranız:</th>
                <td><?php echo htmlspecialchars($musteri_bilgileri['telefon'] ?: '- Belirtilmemiş -'); ?></td>
            </tr>
            <tr>
                <th>Adresiniz:</th>
                <td><?php echo nl2br(htmlspecialchars($musteri_bilgileri['adres'] ?: '- Belirtilmemiş -')); ?></td>
            </tr>
            <tr>
                <th>Kayıt Tarihiniz:</th>
                <td><?php echo date('d F Y, H:i', strtotime($musteri_bilgileri['kayit_tarihi'])); ?></td>
            </tr>
        </table>
        <p style="margin-top:20px;">
            <!-- <a href="bilgilerimi_guncelle.php" class="checkout-button" style="background-color:#007bff;">Bilgilerimi Güncelle</a> -->
            <!-- <a href="sifre_degistir.php" class="checkout-button" style="background-color:#ffc107; color:#212529; margin-left:10px;">Şifremi Değiştir</a> -->
            <!-- Bu linkler için ayrı sayfalar veya bu sayfada formlar oluşturulacak -->
        </p>

        <hr style="margin: 30px 0;">

        <h2>Sipariş Geçmişim</h2>
        <p class="message info">Sipariş geçmişi özelliği yakında eklenecektir.</p>
        <!-- Buraya müşterinin geçmiş siparişlerini listeleyecek kod gelecek -->

    <?php elseif(empty($hata_mesaji_hesabim)): ?>
        <p class="message warning">Hesap bilgileriniz şu anda görüntülenemiyor.</p>
    <?php endif; ?>

</div>

<?php
require_once 'includes/footer_site.php';
?>