<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

$kategori_urunleri = [];
$kategori_bilgisi = null;
$hata_mesaji_kategori = "";

// URL'den kategori ID'sini al
if (isset($_GET['kategori_id']) && is_numeric($_GET['kategori_id'])) {
    $gosterilecek_kategori_id = intval($_GET['kategori_id']);

    if (isset($pdo)) {
        try {
            // 1. Kategori bilgilerini çek (kategori adını başlıkta göstermek için)
            $stmt_kategori_bilgi = $pdo->prepare("SELECT kategori_id, kategori_adi, aciklama FROM Kategoriler WHERE kategori_id = ?");
            $stmt_kategori_bilgi->execute([$gosterilecek_kategori_id]);
            $kategori_bilgisi = $stmt_kategori_bilgi->fetch();

            if ($kategori_bilgisi) {
                // 2. Bu kategoriye ait ürünleri çek
                $stmt_kategori_urunler = $pdo->prepare(
                    "SELECT u.urun_id, u.urun_adi, u.fiyat, u.stok_miktari, u.resim_yolu, k.kategori_adi
                     FROM Urunler u
                     INNER JOIN Kategoriler k ON u.kategori_id = k.kategori_id
                     WHERE u.kategori_id = ? AND u.stok_miktari > 0 -- Sadece stokta olanları göster
                     ORDER BY u.urun_adi ASC" // Veya eklenme_tarihi DESC vb.
                );
                $stmt_kategori_urunler->execute([$gosterilecek_kategori_id]);
                $kategori_urunleri = $stmt_kategori_urunler->fetchAll();

                if (empty($kategori_urunleri)) {
                    $hata_mesaji_kategori = htmlspecialchars($kategori_bilgisi['kategori_adi']) . " kategorisinde şu anda gösterilecek ürün bulunmamaktadır.";
                }
            } else {
                $hata_mesaji_kategori = "Belirtilen kategori bulunamadı.";
            }
        } catch (PDOException $e) {
            error_log("Kategori ürün çekme hatası (kategori_urunler.php): " . $e->getMessage());
            $hata_mesaji_kategori = "Ürünler yüklenirken bir veritabanı sorunu oluştu.";
        }
    } else {
        $hata_mesaji_kategori = "Veritabanı bağlantısı kurulamadı.";
    }
} else {
    // Geçerli bir kategori ID'si gelmemişse
    $hata_mesaji_kategori = "Geçerli bir kategori seçilmedi.";
    // Ana sayfaya yönlendirme de yapılabilir
    // yonlendir('index.php');
}
?>

<div class="ana-container sayfa-icerik">

    <?php if ($kategori_bilgisi): ?>
        <h1><?php echo htmlspecialchars($kategori_bilgisi['kategori_adi']); ?></h1>
        <?php if (!empty($kategori_bilgisi['aciklama'])): ?>
            <p class="kategori-aciklama" style="font-size: 0.95em; color: #555; margin-bottom: 25px; text-align:center;"><?php echo nl2br(htmlspecialchars($kategori_bilgisi['aciklama'])); ?></p>
        <?php endif; ?>
    <?php else: ?>
        <!-- Eğer kategori bilgisi çekilemediyse ama hata mesajı varsa (örn: ID yok) -->
        <h1>Ürünler</h1> <!-- Genel bir başlık -->
    <?php endif; ?>


    <?php if (!empty($hata_mesaji_kategori)): ?>
        <p class="message <?php echo (strpos($hata_mesaji_kategori, "bulunmamaktadır") !== false) ? 'info' : 'error'; ?>">
            <?php echo htmlspecialchars($hata_mesaji_kategori); ?>
        </p>
        <?php if (strpos($hata_mesaji_kategori, "bulunmamaktadır") === false) : // Sadece "ürün yok" dışındaki hatalarda ana sayfa linki ?>
            <p><a href="index.php" class="checkout-button" style="background-color:#007bff;">Tüm Ürünlere Göz At</a></p>
        <?php endif; ?>
    <?php endif; ?>


    <?php if (!empty($kategori_urunleri)): ?>
        <div class="urun-listesi">
            <?php foreach ($kategori_urunleri as $urun_kat): ?>
                <div class="urun-karti">
                    <a href="urun_detay.php?urun_id=<?php echo htmlspecialchars($urun_kat['urun_id']); ?>">
                        <img src="<?php echo htmlspecialchars($urun_kat['resim_yolu'] ? 'assets/images/products/' . $urun_kat['resim_yolu'] : 'assets/images/placeholder.png'); ?>"
                             alt="<?php echo htmlspecialchars($urun_kat['urun_adi']); ?>">
                        <h3><?php echo htmlspecialchars($urun_kat['urun_adi']); ?></h3>
                    </a>
                    <!-- Kategori adını tekrar yazdırmaya gerek yok, zaten kategori sayfasındayız -->
                    <p class="fiyat"><?php echo number_format($urun_kat['fiyat'], 2, ',', '.'); ?> TL</p>
                    <p>Stok: <?php echo htmlspecialchars($urun_kat['stok_miktari']); ?> adet</p>

                    <?php if ($urun_kat['stok_miktari'] > 0): ?>
                        <form action="sepet_islemleri.php" method="post">
                            <input type="hidden" name="urun_id_form" value="<?php echo $urun_kat['urun_id']; ?>">
                            <input type="number" name="miktar_form" value="1" min="1" max="<?php echo $urun_kat['stok_miktari']; ?>">
                            <button type="submit" name="eylem_sepete_ekle">Sepete Ekle</button>
                        </form>
                    <?php else: ?>
                        <p class="message warning">Bu ürün şu anda stokta bulunmamaktadır.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($hata_mesaji_kategori) && $kategori_bilgisi && empty($kategori_urunleri)):
        // Bu blok, hata mesajı yok, kategori bilgisi var ama ürün yoksa çalışır.
        // Ancak bu durum zaten $hata_mesaji_kategori'nin "ürün bulunmamaktadır" içermesiyle yukarıda ele alınmış olmalı.
        // Yine de bir fallback olarak eklenebilir.
        // echo '<p class="message info">' . htmlspecialchars($kategori_bilgisi['kategori_adi']) . ' kategorisinde şu anda gösterilecek ürün bulunmamaktadır.</p>';
    ?>
    <?php endif; ?>

</div>

<?php
require_once 'includes/footer_site.php';
?>