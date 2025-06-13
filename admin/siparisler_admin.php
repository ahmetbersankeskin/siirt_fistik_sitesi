<?php
require_once 'includes_admin/header_admin.php'; // $pdo ve fonksiyonlar gelir

// ----- SİPARİŞ DURUMU GÜNCELLEME İŞLEMİ -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eylem_durum_guncelle']) && isset($_POST['siparis_id_form']) && isset($_POST['yeni_siparis_durumu_form'])) {
    if (isset($pdo)) {
        $guncellenecek_siparis_id = intval($_POST['siparis_id_form']);
        $yeni_durum = veri_temizle($_POST['yeni_siparis_durumu_form']);
        $gecerli_durumlar = ['Beklemede', 'Onaylandı', 'Hazırlanıyor', 'Kargolandı', 'Teslim Edildi', 'İptal Edildi', 'İade Edildi']; // İzin verilen durumlar

        if (in_array($yeni_durum, $gecerli_durumlar)) {
            try {
                $stmt_durum_guncelle = $pdo->prepare("UPDATE Siparisler SET siparis_durumu = ? WHERE siparis_id = ?");
                $stmt_durum_guncelle->execute([$yeni_durum, $guncellenecek_siparis_id]);

                if ($stmt_durum_guncelle->rowCount() > 0) {
                    mesaj_ayarla('siparis_admin_mesaj', "Sipariş (ID: {$guncellenecek_siparis_id}) durumu başarıyla '{$yeni_durum}' olarak güncellendi.", 'success');
                } else {
                    mesaj_ayarla('siparis_admin_mesaj', "Sipariş (ID: {$guncellenecek_siparis_id}) bulunamadı veya durum zaten '{$yeni_durum}' idi.", 'warning');
                }
            } catch (PDOException $e) {
                error_log("Sipariş durum güncelleme hatası: " . $e->getMessage());
                mesaj_ayarla('siparis_admin_mesaj', "Sipariş durumu güncellenirken bir veritabanı hatası oluştu.", 'error');
            }
        } else {
            mesaj_ayarla('siparis_admin_mesaj', "Geçersiz sipariş durumu seçildi.", 'error');
        }
    } else {
        mesaj_ayarla('siparis_admin_mesaj', "Veritabanı bağlantısı kurulamadı.", 'error');
    }
    // Sayfayı yenilemeye gerek yok, mesaj zaten gösterilecek ve liste güncel olacak
    // yonlendir('siparisler_admin.php');
}


// ----- SİPARİŞLERİ LİSTELEME -----
$siparisler_listesi = [];
$hata_mesaji_siparisler = "";

// Sayfalama için değişkenler (opsiyonel, şimdilik tümünü listeleyelim)
// $sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
// $kayit_sayisi_sayfa_basi = 10;
// $baslangic_kaydi = ($sayfa - 1) * $kayit_sayisi_sayfa_basi;

if (isset($pdo)) {
    try {
        // Müşteri bilgilerini de almak için JOIN yapalım
        $sql_siparis_cek = "SELECT s.siparis_id, s.siparis_tarihi, s.toplam_tutar, s.siparis_durumu, s.odeme_yontemi,
                                   m.ad AS musteri_ad, m.soyad AS musteri_soyad, m.email AS musteri_email
                            FROM Siparisler s
                            INNER JOIN Musteriler m ON s.musteri_id = m.musteri_id
                            ORDER BY s.siparis_tarihi DESC";
        // Sayfalama eklenecekse: $sql_siparis_cek .= " LIMIT {$baslangic_kaydi}, {$kayit_sayisi_sayfa_basi}";

        $stmt_siparisler_cek = $pdo->query($sql_siparis_cek);
        $siparisler_listesi = $stmt_siparisler_cek->fetchAll();
    } catch (PDOException $e) {
        error_log("Sipariş listeleme hatası (siparisler_admin.php): " . $e->getMessage());
        $hata_mesaji_siparisler = "Siparişler yüklenirken bir veritabanı sorunu oluştu.";
    }
} else {
    $hata_mesaji_siparisler = "Veritabanı bağlantısı kurulamadı.";
}
?>

<h1>Sipariş Yönetimi</h1>

<?php mesaj_goster('siparis_admin_mesaj'); ?>

<?php if (!empty($hata_mesaji_siparisler)): ?>
    <p class="message error"><?php echo htmlspecialchars($hata_mesaji_siparisler); ?></p>
<?php endif; ?>

<?php if (empty($siparisler_listesi) && empty($hata_mesaji_siparisler)): ?>
    <p class="message info">Sistemde henüz hiç sipariş bulunmamaktadır.</p>
<?php elseif (!empty($siparisler_listesi)): ?>
    <table>
        <thead>
            <tr>
                <th>Sip. ID</th>
                <th>Müşteri</th>
                <th>Sipariş Tarihi</th>
                <th style="text-align:right;">Toplam Tutar</th>
                <th>Ödeme Yönt.</th>
                <th>Durumu</th>
                <th>Durum Değiştir</th>
                <th>Detaylar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($siparisler_listesi as $siparis): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($siparis['siparis_id']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($siparis['musteri_ad'] . ' ' . $siparis['musteri_soyad']); ?><br>
                        <small><?php echo htmlspecialchars($siparis['musteri_email']); ?></small>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($siparis['siparis_tarihi'])); ?></td>
                    <td style="text-align:right;"><?php echo number_format($siparis['toplam_tutar'], 2, ',', '.'); ?> TL</td>
                    <td><?php echo htmlspecialchars($siparis['odeme_yontemi']); ?></td>
                    <td>
                        <span class="siparis-durum <?php echo strtolower(str_replace(' ', '-', $siparis['siparis_durumu'])); ?>"
                              style="padding: 5px 8px; border-radius: 4px; color: white; font-size:0.85em;
                                     background-color: <?php
                                        switch ($siparis['siparis_durumu']) {
                                            case 'Beklemede': echo '#ffc107; color:#212529;'; break;
                                            case 'Onaylandı': echo '#17a2b8;'; break;
                                            case 'Hazırlanıyor': echo '#007bff;'; break;
                                            case 'Kargolandı': echo '#6f42c1;'; break;
                                            case 'Teslim Edildi': echo '#28a745;'; break;
                                            case 'İptal Edildi': echo '#dc3545;'; break;
                                            case 'İade Edildi': echo '#fd7e14;'; break;
                                            default: echo '#6c757d;'; break;
                                        }
                                     ?>">
                            <?php echo htmlspecialchars($siparis['siparis_durumu']); ?>
                        </span>
                    </td>
                    <td>
                        <form action="siparisler_admin.php" method="post" style="display:flex; gap:5px; align-items:center;">
                            <input type="hidden" name="siparis_id_form" value="<?php echo $siparis['siparis_id']; ?>">
                            <select name="yeni_siparis_durumu_form" style="padding:5px; font-size:0.9em;">
                                <?php
                                $tum_durumlar = ['Beklemede', 'Onaylandı', 'Hazırlanıyor', 'Kargolandı', 'Teslim Edildi', 'İptal Edildi', 'İade Edildi'];
                                foreach ($tum_durumlar as $durum_opsiyon) {
                                    echo "<option value=\"{$durum_opsiyon}\"" . ($siparis['siparis_durumu'] == $durum_opsiyon ? ' selected disabled' : '') . ">".$durum_opsiyon."</option>";
                                }
                                ?>
                            </select>
                            <button type="submit" name="eylem_durum_guncelle" style="padding:6px 10px; font-size:0.9em;" title="Durumu Güncelle">✔</button> <!-- Checkmark icon -->
                        </form>
                    </td>
                    <td class="action-links" style="text-align:center;">
                        <a href="siparis_detay_admin.php?siparis_id=<?php echo $siparis['siparis_id']; ?>" class="view-link">Görüntüle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Sayfalama linkleri buraya eklenebilir -->
<?php endif; ?>

<?php
require_once 'includes_admin/footer_admin.php';
?>