<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

$urun_detayi = null;
$hata_mesaji_urun_detay = "";

// URL'den ürün ID'sini al
if (isset($_GET['urun_id']) && is_numeric($_GET['urun_id'])) {
    $gosterilecek_urun_id = intval($_GET['urun_id']);

    if (isset($pdo)) {
        try {
            $stmt_urun_detay = $pdo->prepare(
                "SELECT u.urun_id, u.urun_adi, u.aciklama AS urun_aciklama, u.fiyat, u.stok_miktari, u.resim_yolu,
                        k.kategori_id, k.kategori_adi
                 FROM Urunler u
                 INNER JOIN Kategoriler k ON u.kategori_id = k.kategori_id
                 WHERE u.urun_id = ?"
            );
            $stmt_urun_detay->execute([$gosterilecek_urun_id]);
            $urun_detayi = $stmt_urun_detay->fetch();

            if (!$urun_detayi) {
                $hata_mesaji_urun_detay = "Ürün bulunamadı veya artık mevcut değil.";
            }
        } catch (PDOException $e) {
            error_log("Ürün detay çekme hatası (urun_detay.php): " . $e->getMessage());
            $hata_mesaji_urun_detay = "Ürün bilgileri yüklenirken bir veritabanı sorunu oluştu.";
        }
    } else {
        $hata_mesaji_urun_detay = "Veritabanı bağlantısı kurulamadı.";
    }
} else {
    // Geçerli bir ürün ID'si gelmemişse
    $hata_mesaji_urun_detay = "Geçersiz ürün isteği. Lütfen doğru bir ürün seçin.";
    // Bu durumda ana sayfaya yönlendirme de yapılabilir
    // yonlendir('index.php');
}
?>

<div class="ana-container sayfa-icerik">

    <?php if (!empty($hata_mesaji_urun_detay)): ?>
        <p class="message error"><?php echo htmlspecialchars($hata_mesaji_urun_detay); ?></p>
        <p><a href="index.php" class="checkout-button" style="background-color:#007bff;">Ana Sayfaya Dön</a></p>
    <?php elseif ($urun_detayi): ?>
        <div class="urun-detay-wrapper" style="display: flex; flex-wrap: wrap; gap: 30px;">
            <!-- Ürün Resmi Alanı -->
            <div class="urun-detay-resim" style="flex: 1; min-width: 300px; text-align:center;">
                <img src="<?php echo htmlspecialchars($urun_detayi['resim_yolu'] ? 'assets/images/products/' . $urun_detayi['resim_yolu'] : 'assets/images/placeholder.png'); ?>"
                     alt="<?php echo htmlspecialchars($urun_detayi['urun_adi']); ?>"
                     style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #dee2e6; max-height:450px;">
            </div>

            <!-- Ürün Bilgileri Alanı -->
            <div class="urun-detay-bilgi" style="flex: 2; min-width: 300px;">
                <h1><?php echo htmlspecialchars($urun_detayi['urun_adi']); ?></h1>
                <p style="font-size: 0.9em; color: #6c757d;">
                    Kategori: <a href="kategori_urunler.php?kategori_id=<?php echo htmlspecialchars($urun_detayi['kategori_id']); ?>"><?php echo htmlspecialchars($urun_detayi['kategori_adi']); ?></a>
                </p>

                <p class="fiyat" style="font-size: 2em; margin-bottom: 20px;"><?php echo number_format($urun_detayi['fiyat'], 2, ',', '.'); ?> TL</p>

                <p><strong>Stok Durumu:</strong>
                    <?php if ($urun_detayi['stok_miktari'] > 0): ?>
                        <span style="color: green;"><?php echo htmlspecialchars($urun_detayi['stok_miktari']); ?> adet mevcut</span>
                    <?php else: ?>
                        <span style="color: red;">Tükendi</span>
                    <?php endif; ?>
                </p>

                <?php if ($urun_detayi['stok_miktari'] > 0): ?>
                    <form action="sepet_islemleri.php" method="post" style="margin-top: 20px; display: flex; align-items: center; gap: 15px; max-width:300px;">
                        <input type="hidden" name="urun_id_form" value="<?php echo htmlspecialchars($urun_detayi['urun_id']); ?>">
                        <div>
                            <label for="miktar_detay_form_id" style="display:block; margin-bottom:5px; font-size:0.9em;">Miktar:</label>
                            <input type="number" id="miktar_detay_form_id" name="miktar_form" value="1" min="1" max="<?php echo htmlspecialchars($urun_detayi['stok_miktari']); ?>" style="width: 80px; padding: 10px; font-size: 1em;">
                        </div>
                        <button type="submit" name="eylem_sepete_ekle" class="checkout-button" style="padding: 12px 20px; font-size:1em; margin-top:20px;">Sepete Ekle</button>
                    </form>
                <?php else: ?>
                    <p class="message warning" style="margin-top: 20px;">Bu ürün geçici olarak stoklarımızda bulunmamaktadır.</p>
                <?php endif; ?>

                <h3 style="margin-top: 30px;">Ürün Açıklaması</h3>
                <div class="urun-aciklamasi" style="font-size: 0.95em; line-height: 1.7; color: #495057;">
                    <?php echo nl2br(htmlspecialchars($urun_detayi['urun_aciklama'] ?: 'Bu ürün için henüz bir açıklama girilmemiştir.')); ?>
                </div>
            </div>
        </div>

        <!-- İlgili Diğer Ürünler (Opsiyonel - İleride Eklenebilir) -->
        <!--
        <hr style="margin: 40px 0;">
        <h2>Bunları da Beğenebilirsiniz</h2>
        <div class="urun-listesi">
            <?php // Aynı kategoriden veya benzer ürünleri burada listeleyebilirsiniz ?>
        </div>
        -->

    <?php else: ?>
        <!-- $hata_mesaji_urun_detay boş ama $urun_detayi da yoksa (bu durum normalde olmamalı) -->
        <p class="message info">İstenen ürün bilgileri görüntülenemiyor.</p>
        <p><a href="index.php" class="checkout-button" style="background-color:#007bff;">Ana Sayfaya Dön</a></p>
    <?php endif; ?>

</div>

<?php
require_once 'includes/footer_site.php';
?>