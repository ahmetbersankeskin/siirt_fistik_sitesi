<?php
require_once 'includes_admin/header_admin.php'; // $pdo ve fonksiyonlar gelir

// ----- DEĞİŞKEN TANIMLAMALARI -----
$duzenleme_modu = false; // Form düzenleme modunda mı?
$kategori_id_duzenle = null;
$form_kategori_adi = '';
$form_aciklama = '';
$hatalar_kategori = [];

// ----- DÜZENLEME İÇİN KATEGORİ BİLGİLERİNİ ÇEKME -----
if (isset($_GET['eylem']) && $_GET['eylem'] == 'duzenle' && isset($_GET['kategori_id']) && is_numeric($_GET['kategori_id'])) {
    $duzenleme_modu = true;
    $kategori_id_duzenle = intval($_GET['kategori_id']);

    if (isset($pdo)) {
        try {
            $stmt_duzenle_cek = $pdo->prepare("SELECT kategori_adi, aciklama FROM Kategoriler WHERE kategori_id = ?");
            $stmt_duzenle_cek->execute([$kategori_id_duzenle]);
            $kategori_duzenle_verisi = $stmt_duzenle_cek->fetch();

            if ($kategori_duzenle_verisi) {
                $form_kategori_adi = $kategori_duzenle_verisi['kategori_adi'];
                $form_aciklama = $kategori_duzenle_verisi['aciklama'];
            } else {
                mesaj_ayarla('kategori_admin_mesaj', "Düzenlenecek kategori (ID: {$kategori_id_duzenle}) bulunamadı.", 'error');
                $duzenleme_modu = false; // Hata varsa düzenleme modundan çık
            }
        } catch (PDOException $e) {
            error_log("Kategori düzenleme için veri çekme hatası: " . $e->getMessage());
            mesaj_ayarla('kategori_admin_mesaj', "Kategori bilgileri yüklenirken bir hata oluştu.", 'error');
            $duzenleme_modu = false;
        }
    }
}

// ----- FORM GÖNDERİLDİĞİNDE (EKLEME VEYA GÜNCELLEME) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $form_kategori_adi = veri_temizle($_POST['kategori_adi_form']);
    $form_aciklama = veri_temizle($_POST['aciklama_form'] ?? null); // Opsiyonel

    // Doğrulama
    if (empty($form_kategori_adi)) {
        $hatalar_kategori['kategori_adi'] = "Kategori adı boş bırakılamaz.";
    } else {
        // Kategori adının benzersizliğini kontrol et (düzenleme sırasında kendi adı hariç)
        $sql_benzersiz_kontrol = "SELECT kategori_id FROM Kategoriler WHERE kategori_adi = ?";
        $parametreler_benzersiz = [$form_kategori_adi];
        if ($duzenleme_modu && isset($_POST['kategori_id_form_hidden'])) { // Düzenleme formundan gelen ID'yi al
            $guncellenen_kategori_id = intval($_POST['kategori_id_form_hidden']);
            $sql_benzersiz_kontrol .= " AND kategori_id != ?";
            $parametreler_benzersiz[] = $guncellenen_kategori_id;
        }
        $stmt_benzersiz = $pdo->prepare($sql_benzersiz_kontrol);
        $stmt_benzersiz->execute($parametreler_benzersiz);
        if ($stmt_benzersiz->fetch()) {
            $hatalar_kategori['kategori_adi'] = "Bu kategori adı zaten mevcut.";
        }
    }

    if (empty($hatalar_kategori)) {
        try {
            if (isset($_POST['eylem_kategori_guncelle']) && isset($_POST['kategori_id_form_hidden'])) { // GÜNCELLEME İŞLEMİ
                $guncellenecek_id = intval($_POST['kategori_id_form_hidden']);
                $stmt_guncelle = $pdo->prepare("UPDATE Kategoriler SET kategori_adi = ?, aciklama = ? WHERE kategori_id = ?");
                $stmt_guncelle->execute([$form_kategori_adi, $form_aciklama, $guncellenecek_id]);
                mesaj_ayarla('kategori_admin_mesaj', "Kategori (ID: {$guncellenecek_id}) başarıyla güncellendi.", 'success');
            } elseif (isset($_POST['eylem_kategori_ekle'])) { // EKLEME İŞLEMİ
                $stmt_ekle = $pdo->prepare("INSERT INTO Kategoriler (kategori_adi, aciklama) VALUES (?, ?)");
                $stmt_ekle->execute([$form_kategori_adi, $form_aciklama]);
                mesaj_ayarla('kategori_admin_mesaj', "Yeni kategori başarıyla eklendi.", 'success');
            }
            // Başarılı işlem sonrası formu temizle ve düzenleme modundan çık
            $form_kategori_adi = '';
            $form_aciklama = '';
            $duzenleme_modu = false;
            $kategori_id_duzenle = null;
            // Sayfayı yenilemek yerine mesaj_goster() zaten header'da çalışacak
            // yonlendir('kategoriler_admin.php'); // Bu, formu temiz tutar ama mesajı kaçırabiliriz.
        } catch (PDOException $e) {
            error_log("Kategori Ekleme/Güncelleme hatası: " . $e->getMessage());
            mesaj_ayarla('kategori_admin_form_mesaj', "İşlem sırasında bir veritabanı hatası oluştu.", 'error');
        }
    } else {
        mesaj_ayarla('kategori_admin_form_mesaj', "Lütfen formdaki hataları düzeltin.", 'error');
    }
}


// ----- KATEGORİ SİLME İŞLEMİ -----
if (isset($_GET['eylem']) && $_GET['eylem'] == 'sil' && isset($_GET['kategori_id']) && is_numeric($_GET['kategori_id'])) {
    $silinecek_kategori_id = intval($_GET['kategori_id']);
    if (isset($pdo)) {
        // Bu kategoriye ait ürün var mı kontrol et
        $stmt_urun_var_mi = $pdo->prepare("SELECT COUNT(*) FROM Urunler WHERE kategori_id = ?");
        $stmt_urun_var_mi->execute([$silinecek_kategori_id]);
        $urun_sayisi_bu_kategoride = $stmt_urun_var_mi->fetchColumn();

        if ($urun_sayisi_bu_kategoride > 0) {
            mesaj_ayarla('kategori_admin_mesaj', "Bu kategoriye (ID: {$silinecek_kategori_id}) ait {$urun_sayisi_bu_kategoride} adet ürün bulunmaktadır. Kategoriyi silebilmek için önce bu ürünleri başka bir kategoriye atamanız veya silmeniz gerekir.", 'error');
        } else {
            try {
                $stmt_sil = $pdo->prepare("DELETE FROM Kategoriler WHERE kategori_id = ?");
                $stmt_sil->execute([$silinecek_kategori_id]);
                if ($stmt_sil->rowCount() > 0) {
                    mesaj_ayarla('kategori_admin_mesaj', "Kategori (ID: {$silinecek_kategori_id}) başarıyla silindi.", 'success');
                } else {
                    mesaj_ayarla('kategori_admin_mesaj', "Kategori (ID: {$silinecek_kategori_id}) bulunamadı veya silinemedi.", 'warning');
                }
            } catch (PDOException $e) {
                error_log("Kategori silme hatası: " . $e->getMessage());
                mesaj_ayarla('kategori_admin_mesaj', "Kategori silinirken bir veritabanı hatası oluştu.", 'error');
            }
        }
    } else {
        mesaj_ayarla('kategori_admin_mesaj', "Veritabanı bağlantısı kurulamadı.", 'error');
    }
    yonlendir('kategoriler_admin.php'); // İşlem sonrası sayfayı yenile
}


// ----- MEVCUT KATEGORİLERİ LİSTELEME -----
$kategoriler_listesi = [];
if (isset($pdo)) {
    try {
        $stmt_kategoriler_cek = $pdo->query("SELECT kategori_id, kategori_adi, aciklama FROM Kategoriler ORDER BY kategori_adi ASC");
        $kategoriler_listesi = $stmt_kategoriler_cek->fetchAll();
    } catch (PDOException $e) {
        error_log("Kategori listeleme hatası: " . $e->getMessage());
        mesaj_ayarla('kategori_admin_mesaj', "Kategoriler yüklenirken bir hata oluştu.", 'error');
    }
}
?>

<h1>Kategori Yönetimi</h1>
<?php mesaj_goster('kategori_admin_form_mesaj'); // Form işlemleri için mesaj alanı ?>

<div style="background:#fff; padding:20px; border-radius:5px; box-shadow:0 2px 4px rgba(0,0,0,0.1); margin-bottom:30px;">
    <h2><?php echo $duzenleme_modu ? 'Kategoriyi Düzenle' : 'Yeni Kategori Ekle'; ?></h2>
    <form action="kategoriler_admin.php<?php echo $duzenleme_modu ? '?eylem=duzenle&kategori_id='.$kategori_id_duzenle : ''; ?>" method="post" novalidate>
        <?php if ($duzenleme_modu && $kategori_id_duzenle): ?>
            <input type="hidden" name="kategori_id_form_hidden" value="<?php echo htmlspecialchars($kategori_id_duzenle); ?>">
        <?php endif; ?>
        <div>
            <label for="kategori_adi_form_id">Kategori Adı <span style="color:red;">*</span></label>
            <input type="text" id="kategori_adi_form_id" name="kategori_adi_form" value="<?php echo htmlspecialchars($form_kategori_adi); ?>" required>
            <?php if (isset($hatalar_kategori['kategori_adi'])): ?><small class="message error" style="display:block;padding:5px;margin-top:2px;"><?php echo $hatalar_kategori['kategori_adi']; ?></small><?php endif; ?>
        </div>
        <div>
            <label for="aciklama_form_id">Açıklama (Opsiyonel)</label>
            <textarea id="aciklama_form_id" name="aciklama_form" rows="3"><?php echo htmlspecialchars($form_aciklama); ?></textarea>
        </div>
        <div style="margin-top:15px;">
            <?php if ($duzenleme_modu): ?>
                <button type="submit" name="eylem_kategori_guncelle">Kategoriyi Güncelle</button>
                <a href="kategoriler_admin.php" style="margin-left:10px; text-decoration:underline;">İptal Et</a>
            <?php else: ?>
                <button type="submit" name="eylem_kategori_ekle">Yeni Kategori Ekle</button>
            <?php endif; ?>
        </div>
    </form>
</div>


<h2>Mevcut Kategoriler</h2>
<?php mesaj_goster('kategori_admin_mesaj'); // Liste işlemleri için mesaj alanı ?>

<?php if (empty($kategoriler_listesi) && !isset($pdo)): ?>
     <p class="message error">Veritabanı bağlantısı yok, kategoriler listelenemiyor.</p>
<?php elseif (empty($kategoriler_listesi)): ?>
    <p class="message info">Henüz hiç kategori eklenmemiş.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width:5%;">ID</th>
                <th>Kategori Adı</th>
                <th>Açıklama</th>
                <th style="width:15%;">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($kategoriler_listesi as $kategori): ?>
                <tr>
                    <td><?php echo htmlspecialchars($kategori['kategori_id']); ?></td>
                    <td><?php echo htmlspecialchars($kategori['kategori_adi']); ?></td>
                    <td><?php echo htmlspecialchars(mb_strimwidth($kategori['aciklama'] ?? '', 0, 70, "...")); // Açıklamayı kısalt ?></td>
                    <td class="action-links">
                        <a href="kategoriler_admin.php?eylem=duzenle&kategori_id=<?php echo $kategori['kategori_id']; ?>" class="edit-link">Düzenle</a>
                        <a href="kategoriler_admin.php?eylem=sil&kategori_id=<?php echo $kategori['kategori_id']; ?>"
                           class="delete-link"
                           onclick="return confirm('\'<?php echo htmlspecialchars(addslashes($kategori['kategori_adi'])); ?>\' kategorisini silmek istediğinizden emin misiniz? Bu kategoriye ait ürünler varsa silme işlemi başarısız olabilir.');">Sil</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
require_once 'includes_admin/footer_admin.php';
?>