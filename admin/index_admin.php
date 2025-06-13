<?php
// index_admin.php dosyası "admin" klasöründe.
// header_admin.php ve footer_admin.php ise "admin/includes_admin" klasöründe.
require_once 'includes_admin/header_admin.php';
?>

<h1>Gösterge Paneli</h1>
<p>Yönetici paneline hoş geldiniz. Sol taraftaki menüyü kullanarak işlemleri gerçekleştirebilirsiniz.</p>
<p>Bu alanda sitenizle ilgili genel istatistikler veya hızlı erişim linkleri bulunabilir.</p>

<!-- Örnek İstatistik Kutuları (CSS ile daha da güzelleştirilebilir) -->
<div style="display:flex; flex-wrap:wrap; gap:20px; margin-top:30px;">
    <div style="flex:1; min-width:200px; background:#fff; padding:20px; border-radius:5px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        <h4>Toplam Ürün Sayısı</h4>
        <?php
        if(isset($pdo)) {
            $stmt_count = $pdo->query("SELECT COUNT(*) FROM Urunler");
            echo "<p style='font-size:2em; font-weight:bold; color:#2c5e2e;'>" . $stmt_count->fetchColumn() . "</p>";
        }
        ?>
    </div>
    <div style="flex:1; min-width:200px; background:#fff; padding:20px; border-radius:5px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        <h4>Toplam Müşteri</h4>
        <?php
        if(isset($pdo)) {
            $stmt_count = $pdo->query("SELECT COUNT(*) FROM Musteriler");
            echo "<p style='font-size:2em; font-weight:bold; color:#007bff;'>" . $stmt_count->fetchColumn() . "</p>";
        }
        ?>
    </div>
    <div style="flex:1; min-width:200px; background:#fff; padding:20px; border-radius:5px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        <h4>Bekleyen Siparişler</h4>
         <?php
        if(isset($pdo)) {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM Siparisler WHERE siparis_durumu = ?");
            $stmt_count->execute(['Beklemede']); // Veya 'Hazırlanıyor'
            echo "<p style='font-size:2em; font-weight:bold; color:#ffc107;'>" . $stmt_count->fetchColumn() . "</p>";
        }
        ?>
    </div>
</div>

<?php
require_once 'includes_admin/footer_admin.php';
?>