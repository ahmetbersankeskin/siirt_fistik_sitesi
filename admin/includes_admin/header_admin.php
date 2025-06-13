<?php

require_once 'C:/xampp/htdocs/dashboard/siirt_fistik_sitesi/includes/db_baglanti.php';
require_once 'C:/xampp/htdocs/dashboard/siirt_fistik_sitesi/includes/fonksiyonlar.php';
// Eğer yönetici giriş yapmamışsa, login sayfasına yönlendir
if (!admin_giris_yapti_mi()) {
    // Bulunduğumuz dizin admin/includes_admin olduğu için login_admin.php'ye ../login_admin.php ile erişiriz
    yonlendir('../login_admin.php');
}

// Aktif sayfanın adını alalım (menüde vurgulamak için)
$aktif_sayfa = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli - Siirt Keskin Fıstık</title>
    <!-- Ana CSS dosyamız -->
    <link rel="stylesheet" href="C:/xampp/htdocs/dashboard/siirt_fistik_sitesi/assets/css/style.css">
    <style>
        /* Admin paneline özel ek stiller */
        body {
            display: flex;
            margin:0;
            background-color: #f4f7f6;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .admin-sidebar {
            width: 260px;
            background-color: #343a40; /* Koyu sidebar */
            color: #ffffff;
            min-height: 100vh; /* Tam yükseklik */
            padding: 20px 0; /* Üst ve alt padding, yanları 0 */
            box-sizing: border-box;
            position: fixed; /* Sabit yan menü */
            top: 0;
            left: 0;
            overflow-y: auto; /* İçerik taşarsa scroll bar */
            z-index: 1001; /* Üst barın üzerinde kalması için (gerekirse) */
        }
        .admin-sidebar h3 {
            color: #ffffff;
            text-align: center;
            margin-top: 0;
            margin-bottom: 25px;
            padding-bottom: 15px;
            padding-left: 15px; /* h3 için yan boşluk */
            padding-right: 15px; /* h3 için yan boşluk */
            border-bottom: 1px solid #495057;
            font-size: 1.4em;
        }
        .admin-sidebar ul {
            list-style: none;
            padding: 0 15px; /* Liste için yan boşluklar */
            margin: 0;
        }
        .admin-sidebar ul li a {
            color: #adb5bd; /* Açık gri link rengi */
            text-decoration: none;
            display: block;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 8px;
            transition: background-color 0.2s ease, color 0.2s ease;
            font-size:0.95em;
        }
        .admin-sidebar ul li a:hover,
        .admin-sidebar ul li a.aktif { /* Aktif menü elemanı için */
            background-color: #495057; /* Hover ve aktif arka planı */
            color: #ffffff;
        }
        /* İçerik Alanı Wrapper */
        .admin-content-wrapper {
            margin-left: 260px; /* Sidebar genişliği kadar soldan boşluk */
            width: calc(100% - 260px); /* Kalan tüm genişliği al */
            padding: 0; /* Wrapper'ın kendi padding'i olmasın */
            box-sizing: border-box;
            position: relative; /* Üst barı konumlandırmak için */
        }
        /* Üst Bar (Top Bar) */
        .admin-top-bar {
            background-color: #ffffff;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display:flex;
            justify-content:space-between;
            align-items:center;
            /* position: sticky; top: 0; z-index: 1000; // Üste yapışık bar isterseniz */
            /* Eğer sidebar fixed ise, top-bar'ın da normal akışta olması veya
               ona göre konumlandırılması gerekir. Şimdilik normal akışta. */
        }
        .admin-top-bar .admin-welcome {
            font-size: 1.1em;
            color: #333;
        }
        .admin-top-bar .logout-link a {
            background-color: #dc3545; /* Kırmızı çıkış butonu */
            color:white !important; /* style.css'deki a rengini ezmek için */
            padding: 8px 15px;
            border-radius:4px;
            text-decoration:none !important; /* style.css'deki a hover'ını ezmek için */
            font-size:0.9em;
            transition: background-color 0.2s ease;
        }
        .admin-top-bar .logout-link a:hover {
            background-color: #c82333;
            color:white !important;
        }
        /* Asıl İçerik Alanı */
        .admin-main-content {
            padding: 25px 30px; /* İçerik için asıl padding */
        }
        .admin-main-content h1,
        .admin-main-content h2 {
            color: #333;
            margin-top:0; /* Sayfa başlıklarının üst boşluğunu sıfırla */
        }
        /* Admin paneli için tablo stilleri */
        .admin-main-content table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            font-size:0.9em;
        }
        .admin-main-content th, .admin-main-content td {
            border: 1px solid #dee2e6;
            padding: 10px 12px;
            text-align: left;
            vertical-align: middle;
        }
        .admin-main-content th {
            background-color: #e9ecef;
            font-weight: 600;
        }
        .admin-main-content .action-links a {
            margin-right: 8px;
            text-decoration: none !important; /* style.css'deki a hover'ını ezmek için */
            font-size: 0.9em;
            padding: 5px 10px; /* Butonlara padding */
            border-radius:3px;
            color: #fff !important; /* Genel link rengi beyaz */
            display: inline-block; /* Padding'in düzgün görünmesi için */
            margin-bottom: 5px; /* Alt alta gelirse boşluk */
        }
        .admin-main-content .action-links .edit-link { background-color:#007bff; }
        .admin-main-content .action-links .edit-link:hover { background-color:#0056b3; color: #fff !important;}
        .admin-main-content .action-links .delete-link { background-color:#dc3545; }
        .admin-main-content .action-links .delete-link:hover { background-color:#c82333; color: #fff !important;}
        .admin-main-content .action-links .view-link { background-color:#17a2b8; }
        .admin-main-content .action-links .view-link:hover { background-color:#117a8b; color: #fff !important;}

        /* Genel mesaj stillerini de buraya alabiliriz veya style.css'den miras almasını sağlarız */
        .message { padding: 1rem; margin-bottom: 1.5rem; border: 1px solid transparent; border-radius: 0.25rem; font-size: 0.95em; }
        .message.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .message.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .message.info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }
        .message.warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; }
    </style>
</head>
<body>
    <div class="admin-sidebar">
        <h3>Yönetici Paneli</h3>
        <ul>
            <li><a href="index_admin.php" class="<?php echo ($aktif_sayfa == 'index_admin.php' ? 'aktif' : ''); ?>">Gösterge Paneli</a></li>
            <li><a href="urunler_admin.php" class="<?php echo ($aktif_sayfa == 'urunler_admin.php' ? 'aktif' : ''); ?>">Ürün Yönetimi</a></li>
            <li><a href="kategoriler_admin.php" class="<?php echo ($aktif_sayfa == 'kategoriler_admin.php' ? 'aktif' : ''); ?>">Kategori Yönetimi</a></li>
            <li><a href="siparisler_admin.php" class="<?php echo ($aktif_sayfa == 'siparisler_admin.php' ? 'aktif' : ''); ?>">Sipariş Yönetimi</a></li>
            <li><a href="musteriler_admin.php" class="<?php echo ($aktif_sayfa == 'musteriler_admin.php' ? 'aktif' : ''); ?>">Müşteri Yönetimi</a></li>
            <li style="margin-top:20px; border-top: 1px solid #495057; padding-top:10px;">
                <!-- "Siteyi Görüntüle" linki iki seviye yukarıdaki index.php'ye gitmeli -->
                <a href="../../index.php" target="_blank">Siteyi Görüntüle</a>
            </li>
        </ul>
    </div>
    <div class="admin-content-wrapper">
        <div class="admin-top-bar">
             <div class="admin-welcome">Hoş geldiniz, <strong><?php echo isset($_SESSION['yonetici_kullanici_adi_session']) ? htmlspecialchars($_SESSION['yonetici_kullanici_adi_session']) : 'Yönetici'; ?></strong>!</div>
             <div class="logout-link">
                
                  <a href="../logout_admin.php">Çıkış Yap</a>
            </div>
        </div>
        <div class="admin-main-content">
            <!-- Her admin sayfasının özel içeriği buraya gelecek -->