<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

// Sepetteki ürünlerin detaylarını veritabanından çekmek için bir dizi hazırlayalım
$sepet_urunleri_detay = [];
$sepet_bos_mu = true;

if (isset($_SESSION['sepet_session']) && !empty($_SESSION['sepet_session']) && is_array($_SESSION['sepet_session'])) {
    $urun_idler_sepet = array_keys($_SESSION['sepet_session']);

    if (!empty($urun_idler_sepet) && isset($pdo)) {
        $placeholders_sepet = implode(',', array_fill(0, count($urun_idler_sepet), '?'));
        try {
            $stmt_sepet_urunler = $pdo->prepare(
                "SELECT urun_id, urun_adi, fiyat, resim_yolu, stok_miktari
                 FROM Urunler
                 WHERE urun_id IN ($placeholders_sepet)"
            );
            $stmt_sepet_urunler->execute($urun_idler_sepet);
            // Ürünleri urun_id'leri anahtar olacak şekilde diziye alalım
            while ($satir = $stmt_sepet_urunler->fetch()) {
                $sepet_urunleri_detay[$satir['urun_id']] = $satir;
            }
            $sepet_bos_mu = false; // Sepette ürün var (veritabanından da teyit edildi)
        } catch (PDOException $e) {
            error_log("Sepet ürün detayları çekme hatası: " . $e->getMessage());
            mesaj_ayarla('sepet_mesaj', 'Sepet bilgileri yüklenirken bir sorun oluştu.', 'error');
            // Bu durumda sepet boş gibi davranabiliriz veya hata gösterebiliriz
            $sepet_bos_mu = true;
        }
    } elseif (empty($urun_idler_sepet)) {
        $sepet_bos_mu = true;
    }
} else {
    // Session'da sepet_session yoksa veya boşsa
    $sepet_bos_mu = true;
}
?>

<div class="ana-container sayfa-icerik">
    <h1>Alışveriş Sepetiniz</h1>

    <?php mesaj_goster('sepet_mesaj'); // sepet_islemleri.php'den gelen mesajlar burada gösterilecek ?>

    <?php if ($sepet_bos_mu || empty($sepet_urunleri_detay)): ?>
        <p class="message info">Sepetinizde henüz ürün bulunmamaktadır. <a href="index.php">Alışverişe Başlayın!</a></p>
    <?php else: ?>
        <form action="sepet_islemleri.php" method="post">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th style="width:10%;">Resim</th>
                        <th style="width:35%;">Ürün Adı</th>
                        <th style="width:15%;">Birim Fiyat</th>
                        <th style="width:15%;">Miktar</th>
                        <th style="width:15%;">Toplam Fiyat</th>
                        <th style="width:10%;">Kaldır</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['sepet_session'] as $urun_id_session => $urun_session_bilgisi): ?>
                        <?php
                        // Eğer session'daki bir ürün ID'si veritabanından çekilen detaylar arasında yoksa (örn: ürün silinmişse), o ürünü atla
                        if (!isset($sepet_urunleri_detay[$urun_id_session])) {
                            // Bu ürün artık veritabanında yok, sepetten de temizleyebiliriz (opsiyonel)
                            // unset($_SESSION['sepet_session'][$urun_id_session]);
                            continue;
                        }
                        $urun_detay_goster = $sepet_urunleri_detay[$urun_id_session];
                        $istenen_miktar = $urun_session_bilgisi['miktar'];
                        $satir_toplami = $urun_detay_goster['fiyat'] * $istenen_miktar;
                        ?>
                        <tr>
                            <!-- Mobil için data-label ekliyoruz (CSS'te kullanılmıştı) -->
                            <td data-label="Resim">
                                <a href="urun_detay.php?urun_id=<?php echo htmlspecialchars($urun_detay_goster['urun_id']); ?>">
                                    <img src="<?php echo htmlspecialchars($urun_detay_goster['resim_yolu'] ? 'assets/images/products/' . $urun_detay_goster['resim_yolu'] : 'assets/images/placeholder.png'); ?>"
                                         alt="<?php echo htmlspecialchars($urun_detay_goster['urun_adi']); ?>">
                                </a>
                            </td>
                            <td data-label="Ürün Adı">
                                <a href="urun_detay.php?urun_id=<?php echo htmlspecialchars($urun_detay_goster['urun_id']); ?>">
                                    <?php echo htmlspecialchars($urun_detay_goster['urun_adi']); ?>
                                </a>
                                <?php if ($istenen_miktar > $urun_detay_goster['stok_miktari']): ?>
                                    <br><small class="message error" style="padding:3px; font-size:0.8em;">Stokta yetersiz! (Maks: <?php echo $urun_detay_goster['stok_miktari']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Birim Fiyat"><?php echo number_format($urun_detay_goster['fiyat'], 2, ',', '.'); ?> TL</td>
                            <td data-label="Miktar">
                                <input type="number"
                                       name="miktarlar_form[<?php echo htmlspecialchars($urun_detay_goster['urun_id']); ?>]"
                                       value="<?php echo htmlspecialchars($istenen_miktar); ?>"
                                       min="0" <?php // Miktarı 0 yaparak ürünü sepetten kaldırma seçeneği sunar ?>
                                       max="<?php echo htmlspecialchars($urun_detay_goster['stok_miktari']); // Stoktan fazla seçtirmeyiz ?>">
                            </td>
                            <td data-label="Toplam Fiyat"><?php echo number_format($satir_toplami, 2, ',', '.'); ?> TL</td>
                            <td data-label="Kaldır" style="text-align:center;">
                                <a href="sepet_islemleri.php?eylem=urunu_kaldir&urun_id=<?php echo htmlspecialchars($urun_detay_goster['urun_id']); ?>"
                                   class="remove-item"
                                   title="Bu ürünü sepetten kaldır"
                                   onclick="return confirm('Bu ürünü sepetten kaldırmak istediğinizden emin misiniz?');">X</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-actions">
                <button type="submit" name="eylem_sepeti_guncelle" class="checkout-button" style="background-color: #007bff;">Sepeti Güncelle</button>
            </div>
        </form>

        <div class="cart-summary">
            <h3>Genel Toplam: <?php echo number_format(sepet_toplam_tutar($pdo), 2, ',', '.'); ?> TL</h3>
            <a href="odeme.php" class="checkout-button">Ödeme Adımına Geç</a>
        </div>

    <?php endif; ?>
</div>

<?php
require_once 'includes/footer_site.php';
?>