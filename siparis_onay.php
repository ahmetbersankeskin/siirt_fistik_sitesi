<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

// 1. Kullanıcı giriş yapmış mı kontrol et (sipariş veren kullanıcı olmalı)
if (!musteri_giris_yapti_mi()) {
    // Bu sayfaya giriş yapmadan erişilmemeli
    mesaj_ayarla('global_mesaj', 'Sipariş onay sayfasını görüntülemek için lütfen giriş yapınız.', 'error');
    yonlendir('giris_yap.php');
}

// 2. Session'da son sipariş ID'si var mı kontrol et
if (!isset($_SESSION['son_siparis_id_session'])) {
    // Eğer session'da sipariş ID yoksa, muhtemelen doğrudan bu sayfaya gelindi.
    // Ana sayfaya yönlendir.
    mesaj_ayarla('global_mesaj', 'Görüntülenecek bir sipariş onayı bulunmamaktadır.', 'info');
    yonlendir('index.php');
}

$gosterilecek_siparis_id = $_SESSION['son_siparis_id_session'];
$siparis_detaylari_onay = null;
$hata_mesaji_onay = "";

// Sipariş detaylarını veritabanından çek (opsiyonel, sadece ID göstermek de yeterli)
if (isset($pdo)) {
    try {
        $stmt_onay = $pdo->prepare(
            "SELECT s.siparis_id, s.siparis_tarihi, s.toplam_tutar, s.siparis_durumu, s.odeme_yontemi, s.teslimat_adresi,
                    m.ad AS musteri_ad, m.soyad AS musteri_soyad, m.email AS musteri_email
             FROM Siparisler s
             INNER JOIN Musteriler m ON s.musteri_id = m.musteri_id
             WHERE s.siparis_id = ? AND s.musteri_id = ?"
        );
        $stmt_onay->execute([$gosterilecek_siparis_id, get_musteri_id()]); // Sadece kendi siparişini görsün
        $siparis_detaylari_onay = $stmt_onay->fetch();

        if (!$siparis_detaylari_onay) {
            // Sipariş bulunamadı veya bu kullanıcıya ait değil
            error_log("Sipariş Onay: ID $gosterilecek_siparis_id bulunamadı veya kullanıcı " . get_musteri_id() . " ile eşleşmiyor.");
            $hata_mesaji_onay = "Sipariş detayları bulunamadı veya bu siparişi görüntüleme yetkiniz yok.";
            // Güvenlik için session'daki sipariş ID'sini temizle
            unset($_SESSION['son_siparis_id_session']);
        } else {
            // Sipariş başarıyla görüntülendi, session'daki ID'yi temizleyebiliriz
            // Böylece sayfa yenilendiğinde aynı onay tekrar gösterilmez (veya gösterilmeye devam edebilir, tercihe bağlı)
             unset($_SESSION['son_siparis_id_session']); // Temizle
             mesaj_ayarla('onay_sayfasi_mesaj', 'Siparişiniz başarıyla alındı!', 'success');
        }

    } catch (PDOException $e) {
        error_log("Sipariş onay detay çekme hatası (siparis_onay.php): " . $e->getMessage());
        $hata_mesaji_onay = "Sipariş detayları yüklenirken bir veritabanı sorunu oluştu.";
    }
} else {
    $hata_mesaji_onay = "Veritabanı bağlantısı kurulamadı.";
}

?>

<div class="ana-container sayfa-icerik">

    <?php if (!empty($hata_mesaji_onay)): ?>
        <h1>Sipariş Onayı Hatası</h1>
        <p class="message error"><?php echo htmlspecialchars($hata_mesaji_onay); ?></p>
        <p><a href="index.php" class="checkout-button" style="background-color:#007bff;">Ana Sayfaya Dön</a></p>
    <?php elseif ($siparis_detaylari_onay): ?>
        <h1>Siparişiniz Başarıyla Alındı!</h1>
        <?php mesaj_goster('onay_sayfasi_mesaj'); ?>

        <div class="message success" style="padding: 20px; text-align: left;">
            <p>Değerli <strong><?php echo htmlspecialchars($siparis_detaylari_onay['musteri_ad'] . ' ' . $siparis_detaylari_onay['musteri_soyad']); ?></strong>,</p>
            <p>Siparişiniz için teşekkür ederiz! Aşağıda siparişinizin detaylarını bulabilirsiniz:</p>
            <hr style="margin: 15px 0;">
            <p><strong>Sipariş Numaranız:</strong> #<?php echo htmlspecialchars($siparis_detaylari_onay['siparis_id']); ?></p>
            <p><strong>Sipariş Tarihi:</strong> <?php echo date('d F Y, H:i', strtotime($siparis_detaylari_onay['siparis_tarihi'])); ?></p>
            <p><strong>Toplam Tutar:</strong> <?php echo number_format($siparis_detaylari_onay['toplam_tutar'], 2, ',', '.'); ?> TL</p>
            <p><strong>Ödeme Yöntemi:</strong> <?php echo htmlspecialchars($siparis_detaylari_onay['odeme_yontemi']); ?></p>
            <p><strong>Sipariş Durumu:</strong> <?php echo htmlspecialchars($siparis_detaylari_onay['siparis_durumu']); ?></p>
            <p><strong>Teslimat Adresi:</strong><br><?php echo nl2br(htmlspecialchars($siparis_detaylari_onay['teslimat_adresi'])); ?></p>
            <hr style="margin: 15px 0;">
            <p>Siparişinizi en kısa sürede hazırlayıp kargoya vereceğiz. Sipariş durumunuzu "Hesabım" sayfanızdaki "Siparişlerim" bölümünden takip edebilirsiniz (Bu özellik yakında eklenecektir).</p>
            <p>Herhangi bir sorunuz olursa lütfen bizimle iletişime geçmekten çekinmeyin.</p>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="checkout-button" style="background-color:#007bff; margin-right:10px;">Alışverişe Devam Et</a>
            <a href="hesabim.php" class="checkout-button" style="background-color:#6c757d;">Hesabıma Git</a>
        </div>

    <?php else: ?>
        <!-- Bu durum normalde $hata_mesaji_onay ile yakalanmalı -->
        <h1>Onay Bilgisi Yok</h1>
        <p class="message info">Görüntülenecek bir sipariş onayı bulunmamaktadır veya bir hata oluştu.</p>
        <p><a href="index.php" class="checkout-button" style="background-color:#007bff;">Ana Sayfaya Dön</a></p>
    <?php endif; ?>

</div>

<?php
require_once 'includes/footer_site.php';
?>