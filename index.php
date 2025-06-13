    <?php
require_once 'includes/header_site.php'; // Site üst bilgilerini ve menüyü yükler ($pdo da burada gelir)

$urunler = [];
$hata_mesaji_index = ""; // index.php özelinde hata mesajları için

if (!isset($pdo)) {
    $hata_mesaji_index = "Kritik Hata: Veritabanı bağlantı nesnesi yüklenemedi. Lütfen site yöneticisi ile iletişime geçin.";
} else {
    // $pdo tanımlıysa ürünleri çekmeyi dene
    try {
        $stmt_urunler = $pdo->query(
            "SELECT u.urun_id, u.urun_adi, u.fiyat, u.stok_miktari, u.resim_yolu, k.kategori_adi
             FROM Urunler u
             INNER JOIN Kategoriler k ON u.kategori_id = k.kategori_id
             WHERE u.stok_miktari > 0
             ORDER BY u.eklenme_tarihi DESC
             LIMIT 9"
        );
        $urunler = $stmt_urunler->fetchAll();

        if (empty($urunler)) {
            // Sorgu başarılı ama hiç ürün dönmediyse (stokta ürün yoksa vs.)
            $hata_mesaji_index = "Şu anda gösterilecek aktif ürün bulunmamaktadır.";
        }
    } catch (PDOException $e) {
        error_log("Ana sayfa ürün çekme hatası (index.php): " . $e->getMessage());
        $hata_mesaji_index = "Ürünler yüklenirken bir veritabanı sorunu oluştu.";
    }
}
?>

<!-- Ana sayfa içeriği .ana-container içinde olacak -->
<!-- Bu .ana-container, header_site.php'deki flash mesajlar için olan .ana-container'dan farklıdır. -->
<!-- Her sayfanın kendi ana içerik container'ı olmalı. -->
<div class="ana-container sayfa-icerik"> <!-- CSS'te hedeflemek için ek sınıf: sayfa-icerik -->

    <h1>Hoş Geldiniz! En Taze Siirt Fıstıkları</h1>

    <?php
    // Eğer index.php özelinde bir hata mesajı varsa (veritabanı veya ürün yok mesajı) göster
    if (!empty($hata_mesaji_index)) {
        // Mesajın tipini belirleyelim (info mu error mu)
        $mesaj_tipi = 'error'; // Varsayılan
        if (strpos($hata_mesaji_index, "bulunmamaktadır") !== false) {
            $mesaj_tipi = 'info';
        } elseif (strpos($hata_mesaji_index, "Kritik Hata") !== false) {
            // Kritik hata zaten header'da gösterilmeli veya burada daha belirgin olmalı
            // Şimdilik error olarak kalsın
        }
        echo '<p class="message ' . $mesaj_tipi . '">' . htmlspecialchars($hata_mesaji_index) . '</p>';
    }
    ?>

    <?php if (!empty($urunler)): ?>
        <div class="urun-listesi">
            <?php foreach ($urunler as $urun): ?>
                <div class="urun-karti">
                    <a href="urun_detay.php?urun_id=<?php echo htmlspecialchars($urun['urun_id']); ?>">
                        <img src="<?php echo htmlspecialchars($urun['resim_yolu'] ? 'assets/images/products/' . $urun['resim_yolu'] : 'assets/images/placeholder.png'); ?>"
                             alt="<?php echo htmlspecialchars($urun['urun_adi']); ?>">
                        <h3><?php echo htmlspecialchars($urun['urun_adi']); ?></h3>
                    </a>
                    <p>Kategori: <?php echo htmlspecialchars($urun['kategori_adi']); ?></p>
                    <p class="fiyat"><?php echo number_format($urun['fiyat'], 2, ',', '.'); ?> TL</p>
                    <p>Stok: <?php echo htmlspecialchars($urun['stok_miktari']); ?> adet</p>

                    <?php if ($urun['stok_miktari'] > 0): ?>
                        <form action="sepet_islemleri.php" method="post">
                            <input type="hidden" name="urun_id_form" value="<?php echo $urun['urun_id']; ?>">
                            <input type="number" name="miktar_form" value="1" min="1" max="<?php echo $urun['stok_miktari']; ?>">
                            <button type="submit" name="eylem_sepete_ekle">Sepete Ekle</button>
                        </form>
                    <?php else: ?>
                        <p class="message warning">Bu ürün şu anda stokta bulunmamaktadır.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- Eğer hem $hata_mesaji_index boş hem de $urunler boş ise buraya bir şey yazılmaz.
         Bu durum, $hata_mesaji_index'in "ürün bulunmamaktadır" mesajını içermesiyle zaten ele alınmış olmalı. -->

</div> <!-- .ana-container .sayfa-icerik sonu -->

<?php
require_once 'includes/footer_site.php'; // Site alt bilgilerini yükler
?>