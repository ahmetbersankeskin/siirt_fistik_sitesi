<?php
require_once 'includes_admin/header_admin.php'; // Admin header'ını yükle ($pdo ve fonksiyonlar gelir)

// Müşteri Silme İşlemi
if (isset($_GET['eylem']) && $_GET['eylem'] == 'sil' && isset($_GET['musteri_id']) && is_numeric($_GET['musteri_id'])) {
    $silinecek_musteri_id = intval($_GET['musteri_id']);

    if (isset($pdo)) {
        // ÖNEMLİ GÜVENLİK NOTU: Bir müşteriyi silmeden önce, ona ait siparişler gibi
        // ilişkili verilerin ne olacağına karar vermelisiniz.
        // 1. İlişkili verileri de sil (ON DELETE CASCADE ayarlanmışsa otomatik olabilir).
        // 2. Müşteriyi silmeyi engelle (eğer siparişi varsa).
        // 3. Müşteriyi "pasif" veya "arşivlenmiş" olarak işaretle (daha güvenli bir yöntem).
        // Şimdilik, basitlik adına direkt silme yapacağız, ancak bu canlı bir sistemde dikkatle ele alınmalıdır.

        // Müşteriye ait sipariş var mı kontrol edelim (Örnek bir kontrol)
        $stmt_siparis_kontrol = $pdo->prepare("SELECT COUNT(*) FROM Siparisler WHERE musteri_id = ?");
        $stmt_siparis_kontrol->execute([$silinecek_musteri_id]);
        $siparis_sayisi = $stmt_siparis_kontrol->fetchColumn();

        if ($siparis_sayisi > 0) {
            mesaj_ayarla('musteri_admin_mesaj', "Bu müşteriye ait {$siparis_sayisi} adet sipariş bulunmaktadır. Önce siparişleri silmeniz veya başka bir müşteriye atamanız gerekir. Müşteri silinemedi.", 'error');
        } else {
            try {
                $stmt_musteri_sil = $pdo->prepare("DELETE FROM Musteriler WHERE musteri_id = ?");
                $stmt_musteri_sil->execute([$silinecek_musteri_id]);

                if ($stmt_musteri_sil->rowCount() > 0) {
                    mesaj_ayarla('musteri_admin_mesaj', "Müşteri (ID: {$silinecek_musteri_id}) başarıyla silindi.", 'success');
                } else {
                    mesaj_ayarla('musteri_admin_mesaj', "Müşteri (ID: {$silinecek_musteri_id}) bulunamadı veya silinemedi.", 'warning');
                }
            } catch (PDOException $e) {
                error_log("Müşteri silme hatası (musteriler_admin.php): " . $e->getMessage());
                mesaj_ayarla('musteri_admin_mesaj', "Müşteri silinirken bir veritabanı hatası oluştu.", 'error');
            }
        }
    } else {
        mesaj_ayarla('musteri_admin_mesaj', "Veritabanı bağlantısı kurulamadı.", 'error');
    }
    // İşlem sonrası mesajların gösterilmesi için sayfaya geri yönlendir
    yonlendir('musteriler_admin.php');
}


// Müşterileri Listeleme
$musteriler_listesi = [];
$hata_mesaji_musteriler = "";
if (isset($pdo)) {
    try {
        $stmt_musteriler_cek = $pdo->query("SELECT musteri_id, ad, soyad, email, telefon, kayit_tarihi FROM Musteriler ORDER BY kayit_tarihi DESC");
        $musteriler_listesi = $stmt_musteriler_cek->fetchAll();
    } catch (PDOException $e) {
        error_log("Müşteri listeleme hatası (musteriler_admin.php): " . $e->getMessage());
        $hata_mesaji_musteriler = "Müşteriler yüklenirken bir veritabanı sorunu oluştu.";
    }
} else {
    $hata_mesaji_musteriler = "Veritabanı bağlantısı kurulamadı.";
}
?>

<h1>Müşteri Yönetimi</h1>

<?php mesaj_goster('musteri_admin_mesaj'); // Silme veya diğer işlemlerden gelen mesajlar ?>

<?php if (!empty($hata_mesaji_musteriler)): ?>
    <p class="message error"><?php echo htmlspecialchars($hata_mesaji_musteriler); ?></p>
<?php endif; ?>

<?php if (empty($musteriler_listesi) && empty($hata_mesaji_musteriler)): ?>
    <p class="message info">Sistemde kayıtlı müşteri bulunmamaktadır.</p>
<?php elseif (!empty($musteriler_listesi)): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Ad Soyad</th>
                <th>E-posta</th>
                <th>Telefon</th>
                <th>Kayıt Tarihi</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($musteriler_listesi as $musteri): ?>
                <tr>
                    <td><?php echo htmlspecialchars($musteri['musteri_id']); ?></td>
                    <td><?php echo htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']); ?></td>
                    <td><?php echo htmlspecialchars($musteri['email']); ?></td>
                    <td><?php echo htmlspecialchars($musteri['telefon'] ?: '-'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($musteri['kayit_tarihi'])); ?></td>
                    <td class="action-links">
                        <a href="musteri_duzenle_admin.php?musteri_id=<?php echo $musteri['musteri_id']; ?>" class="edit-link" title="Müşteriyi Düzenle">Düzenle</a>
                        <a href="musteriler_admin.php?eylem=sil&musteri_id=<?php echo $musteri['musteri_id']; ?>"
                           class="delete-link"
                           title="Müşteriyi Sil"
                           onclick="return confirm('Bu müşteriyi (ID: <?php echo $musteri['musteri_id']; ?>) silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve müşteriye ait siparişler varsa sorun çıkarabilir!');">Sil</a>
                        <!-- <a href="musteri_siparisleri_admin.php?musteri_id=<?php echo $musteri['musteri_id']; ?>" class="view-link" title="Müşteri Siparişlerini Görüntüle">Siparişler</a> -->
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
require_once 'includes_admin/footer_admin.php'; // Admin footer'ını yükle
?>