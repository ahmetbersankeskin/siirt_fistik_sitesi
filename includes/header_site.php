<?php
require_once 'db_baglanti.php';
require_once 'fonksiyonlar.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siirt Keskin Fıstık</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="ana-container"> <!-- Header içeriği de ana-container içinde -->
            <nav>
                <div class="logo">
                    <a href="index.php" title="Ana Sayfa">
                        <img src="assets/images/ondeki.png" alt="Siirt Keskin Fıstık Logo">
                        <span>SİİRT KESKİN FISTIK</span>
                    </a>
                </div>
                <ul>
                    <li><a href="index.php">Ana Sayfa</a></li>
                    <?php
                    if (isset($pdo)) {
                        try {
                            $stmt_kategoriler_menu = $pdo->query("SELECT kategori_id, kategori_adi FROM Kategoriler ORDER BY kategori_id ASC LIMIT 4");
                            while ($kategori_menu = $stmt_kategoriler_menu->fetch()) {
                                $menu_kategori_adi = htmlspecialchars($kategori_menu['kategori_adi']);
                                if (mb_strlen($menu_kategori_adi) > 12 && strpos($menu_kategori_adi, '(') !== false) {
                                    $menu_kategori_adi = trim(mb_substr($menu_kategori_adi, 0, mb_strpos($menu_kategori_adi, '(')));
                                } elseif (mb_strlen($menu_kategori_adi) > 12) {
                                    $menu_kategori_adi = mb_substr($menu_kategori_adi, 0, 10) . "...";
                                }
                                echo '<li><a href="kategori_urunler.php?kategori_id=' . htmlspecialchars($kategori_menu['kategori_id']) . '">' . mb_strtoupper($menu_kategori_adi) . '</a></li>';
                            }
                        } catch (PDOException $e) {
                            error_log("Menü kategori çekme hatası (header_site.php): " . $e->getMessage());
                        }
                    }
                    ?>
                    <li><a href="sepet.php">Sepetim (<?php echo sepetteki_urun_sayisi(); ?>)</a></li>
                    <?php if (musteri_giris_yapti_mi()): ?>
                        <li><a href="hesabim.php">Hesabım</a></li>
                        <li><a href="cikis_yap.php">Çıkış Yap</a></li>
                    <?php else: ?>
                        <li><a href="giris_yap.php">Giriş Yap</a></li>
                        <li><a href="kayit_ol.php">Kayıt Ol</a></li>
                    <?php endif; ?>
                    <li><a href="admin/login_admin.php" target="_blank" title="Yönetici Paneli">Admin</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        
        <div class="ana-container">
             <?php mesaj_goster('global_mesaj'); ?>
        </div>
        