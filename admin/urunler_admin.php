<?php
require_once 'includes_admin/header_admin.php'; // $pdo ve fonksiyonlar gelir

// ----- DEĞİŞKEN TANIMLAMALARI VE SABİTLER -----
$duzenleme_modu_urun = false;
$urun_id_duzenle = null;
$form_urun_adi = '';
$form_kategori_id = '';
$form_aciklama_urun = '';
$form_fiyat = '';
$form_stok_miktari = '';
$mevcut_resim_yolu = ''; // Düzenleme sırasında mevcut resmi göstermek için
$hatalar_urun = [];

// Resim yükleme için hedef klasör (ana dizine göre)
define('URUN_RESIM_HEDEF_KLASOR', '../assets/images/products/'); // İki nokta üst üste ile bir üst dizine çıkıp assets'e giriyoruz
define('MAX_DOSYA_BOYUTU', 2 * 1024 * 1024); // 2MB
$izin_verilen_uzantilar = ['jpg', 'jpeg', 'png', 'gif'];


// ----- KATEGORİLERİ ÇEKME (Dropdown için) -----
$kategoriler_form_icin = [];
if (isset($pdo)) {
    try {
        $stmt_kat_form = $pdo->query("SELECT kategori_id, kategori_adi FROM Kategoriler ORDER BY kategori_adi ASC");
        $kategoriler_form_icin = $stmt_kat_form->fetchAll();
    } catch (PDOException $e) {
        error_log("Ürün formu için kategori çekme hatası: " . $e->getMessage());
        mesaj_ayarla('urun_admin_form_mesaj', 'Kategoriler yüklenemedi, ürün ekleyemez/düzenleyemezsiniz.', 'error');
    }
}


// ----- DÜZENLEME İÇİN ÜRÜN BİLGİLERİNİ ÇEKME -----
if (isset($_GET['eylem']) && $_GET['eylem'] == 'duzenle_urun' && isset($_GET['urun_id']) && is_numeric($_GET['urun_id'])) {
    $duzenleme_modu_urun = true;
    $urun_id_duzenle = intval($_GET['urun_id']);

    if (isset($pdo)) {
        try {
            $stmt_urun_duzenle_cek = $pdo->prepare("SELECT * FROM Urunler WHERE urun_id = ?");
            $stmt_urun_duzenle_cek->execute([$urun_id_duzenle]);
            $urun_duzenle_verisi = $stmt_urun_duzenle_cek->fetch();

            if ($urun_duzenle_verisi) {
                $form_urun_adi = $urun_duzenle_verisi['urun_adi'];
                $form_kategori_id = $urun_duzenle_verisi['kategori_id'];
                $form_aciklama_urun = $urun_duzenle_verisi['aciklama'];
                $form_fiyat = $urun_duzenle_verisi['fiyat'];
                $form_stok_miktari = $urun_duzenle_verisi['stok_miktari'];
                $mevcut_resim_yolu = $urun_duzenle_verisi['resim_yolu'];
            } else {
                mesaj_ayarla('urun_admin_mesaj', "Düzenlenecek ürün (ID: {$urun_id_duzenle}) bulunamadı.", 'error');
                $duzenleme_modu_urun = false;
            }
        } catch (PDOException $e) {
            error_log("Ürün düzenleme için veri çekme hatası: " . $e->getMessage());
            mesaj_ayarla('urun_admin_mesaj', "Ürün bilgileri yüklenirken bir hata oluştu.", 'error');
            $duzenleme_modu_urun = false;
        }
    }
}

// ----- FORM GÖNDERİLDİĞİNDE (EKLEME VEYA GÜNCELLEME) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $form_urun_adi = veri_temizle($_POST['urun_adi_form']);
    $form_kategori_id = isset($_POST['kategori_id_form']) ? intval($_POST['kategori_id_form']) : null;
    $form_aciklama_urun = veri_temizle($_POST['aciklama_urun_form'] ?? null);
    $form_fiyat = isset($_POST['fiyat_form']) ? filter_var($_POST['fiyat_form'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
    $form_stok_miktari = isset($_POST['stok_miktari_form']) ? intval($_POST['stok_miktari_form']) : 0;
    $yeni_resim_yolu_db = null; // Veritabanına kaydedilecek resim yolu

    // Düzenleme modundaysak ve yeni resim yüklenmemişse mevcut resmi koru
    if (isset($_POST['eylem_urun_guncelle']) && isset($_POST['urun_id_form_hidden'])) {
        $mevcut_resim_yolu = $_POST['mevcut_resim_yolu_form'] ?? null; // Formdan gelen mevcut resim yolu
        $yeni_resim_yolu_db = $mevcut_resim_yolu; // Varsayılan olarak mevcut resmi kullan
    }


    // Doğrulamalar
    if (empty($form_urun_adi)) $hatalar_urun['urun_adi'] = "Ürün adı boş bırakılamaz.";
    if (empty($form_kategori_id)) $hatalar_urun['kategori_id'] = "Lütfen bir kategori seçin.";
    if (!is_numeric($form_fiyat) || $form_fiyat <= 0) $hatalar_urun['fiyat'] = "Geçerli bir fiyat girin (0'dan büyük).";
    if (!is_numeric($form_stok_miktari) || $form_stok_miktari < 0) $hatalar_urun['stok_miktari'] = "Stok miktarı 0 veya daha büyük bir sayı olmalıdır.";

    // Resim Yükleme İşlemleri
    if (isset($_FILES['resim_form']) && $_FILES['resim_form']['error'] == UPLOAD_ERR_OK) {
        $dosya_gecici_yol = $_FILES['resim_form']['tmp_name'];
        $dosya_adi = basename($_FILES['resim_form']['name']);
        $dosya_boyutu = $_FILES['resim_form']['size'];
        $dosya_tipi = strtolower(pathinfo($dosya_adi, PATHINFO_EXTENSION));

        // Benzersiz dosya adı oluşturma (aynı isimde dosyaların üzerine yazılmasını engellemek için)
        $benzersiz_dosya_adi = uniqid('urun_', true) . '.' . $dosya_tipi;
        $hedef_yol_tam = URUN_RESIM_HEDEF_KLASOR . $benzersiz_dosya_adi;

        if (!in_array($dosya_tipi, $izin_verilen_uzantilar)) {
            $hatalar_urun['resim'] = "Geçersiz dosya uzantısı. Sadece " . implode(', ', $izin_verilen_uzantilar) . " uzantılarına izin verilir.";
        } elseif ($dosya_boyutu > MAX_DOSYA_BOYUTU) {
            $hatalar_urun['resim'] = "Dosya boyutu çok büyük. Maksimum " . (MAX_DOSYA_BOYUTU / 1024 / 1024) . "MB.";
        } else {
            if (move_uploaded_file($dosya_gecici_yol, $hedef_yol_tam)) {
                $yeni_resim_yolu_db = $benzersiz_dosya_adi; // Veritabanına sadece dosya adını kaydet
                // Düzenleme modunda eski resmi sil (eğer varsa ve farklıysa)
                if ($duzenleme_modu_urun && !empty($mevcut_resim_yolu) && $mevcut_resim_yolu != $yeni_resim_yolu_db && file_exists(URUN_RESIM_HEDEF_KLASOR . $mevcut_resim_yolu)) {
                    unlink(URUN_RESIM_HEDEF_KLASOR . $mevcut_resim_yolu);
                }
            } else {
                $hatalar_urun['resim'] = "Resim yüklenirken bir hata oluştu.";
            }
        }
    } elseif (isset($_FILES['resim_form']) && $_FILES['resim_form']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['resim_form']['error'] != UPLOAD_ERR_OK) {
        // Dosya seçilmiş ama UPLOAD_ERR_OK değilse (örn: boyuttan dolayı sunucu reddetmişse)
        $hatalar_urun['resim'] = "Resim yüklenirken bir sunucu hatası oluştu (Hata Kodu: ".$_FILES['resim_form']['error'].").";
    } elseif (!isset($_POST['eylem_urun_guncelle']) && empty($yeni_resim_yolu_db)) { // Ekleme modunda resim seçilmemişse
        // $hatalar_urun['resim'] = "Lütfen bir ürün resmi seçin."; // Resim zorunluysa bu satırı açın
    }


    if (empty($hatalar_urun)) {
        try {
            if (isset($_POST['eylem_urun_guncelle']) && isset($_POST['urun_id_form_hidden'])) { // GÜNCELLEME
                $guncellenecek_id = intval($_POST['urun_id_form_hidden']);
                $stmt_guncelle_urun = $pdo->prepare(
                    "UPDATE Urunler SET urun_adi = ?, kategori_id = ?, aciklama = ?, fiyat = ?, stok_miktari = ?, resim_yolu = ?
                     WHERE urun_id = ?"
                );
                $stmt_guncelle_urun->execute([
                    $form_urun_adi, $form_kategori_id, $form_aciklama_urun, $form_fiyat,
                    $form_stok_miktari, $yeni_resim_yolu_db, $guncellenecek_id
                ]);
                mesaj_ayarla('urun_admin_mesaj', "Ürün (ID: {$guncellenecek_id}) başarıyla güncellendi.", 'success');
            } elseif (isset($_POST['eylem_urun_ekle'])) { // EKLEME
                $stmt_ekle_urun = $pdo->prepare(
                    "INSERT INTO Urunler (urun_adi, kategori_id, aciklama, fiyat, stok_miktari, resim_yolu, eklenme_tarihi)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())"
                );
                $stmt_ekle_urun->execute([
                    $form_urun_adi, $form_kategori_id, $form_aciklama_urun, $form_fiyat,
                    $form_stok_miktari, $yeni_resim_yolu_db
                ]);
                mesaj_ayarla('urun_admin_mesaj', "Yeni ürün başarıyla eklendi.", 'success');
            }
            // Formu temizle ve düzenleme modundan çık
            $form_urun_adi = ''; $form_kategori_id = ''; $form_aciklama_urun = ''; $form_fiyat = '';
            $form_stok_miktari = ''; $mevcut_resim_yolu = ''; $duzenleme_modu_urun = false; $urun_id_duzenle = null;
            // yonlendir('urunler_admin.php'); // Liste güncellenmiş olarak görünmesi için
        } catch (PDOException $e) {
            error_log("Ürün Ekleme/Güncelleme hatası: " . $e->getMessage());
            mesaj_ayarla('urun_admin_form_mesaj', "İşlem sırasında bir veritabanı hatası oluştu: " . $e->getMessage(), 'error');
        }
    } else {
         mesaj_ayarla('urun_admin_form_mesaj', "Lütfen formdaki hataları düzeltin.", 'error');
    }
}


// ----- ÜRÜN SİLME İŞLEMİ -----
if (isset($_GET['eylem']) && $_GET['eylem'] == 'sil_urun' && isset($_GET['urun_id']) && is_numeric($_GET['urun_id'])) {
    $silinecek_urun_id = intval($_GET['urun_id']);
    if (isset($pdo)) {
        // ÖNEMLİ: Bu ürüne ait sipariş detayı varsa ne yapılmalı?
        // Şimdilik sadece ürünü siliyoruz. İlişkili veriler (SiparisDetaylari) için
        // veritabanında ON DELETE kısıtlamaları (örn: RESTRICT veya SET NULL) ayarlanmış olabilir.
        // Eğer SiparisDetaylari.urun_id için ON DELETE RESTRICT varsa, ürün silinemez.
        try {
            // Önce resmini silelim (eğer varsa)
            $stmt_resim_bul = $pdo->prepare("SELECT resim_yolu FROM Urunler WHERE urun_id = ?");
            $stmt_resim_bul->execute([$silinecek_urun_id]);
            $eski_resim = $stmt_resim_bul->fetchColumn();
            if ($eski_resim && file_exists(URUN_RESIM_HEDEF_KLASOR . $eski_resim)) {
                unlink(URUN_RESIM_HEDEF_KLASOR . $eski_resim);
            }

            $stmt_sil_urun = $pdo->prepare("DELETE FROM Urunler WHERE urun_id = ?");
            $stmt_sil_urun->execute([$silinecek_urun_id]);
            if ($stmt_sil_urun->rowCount() > 0) {
                mesaj_ayarla('urun_admin_mesaj', "Ürün (ID: {$silinecek_urun_id}) ve resmi başarıyla silindi.", 'success');
            } else {
                mesaj_ayarla('urun_admin_mesaj', "Ürün (ID: {$silinecek_urun_id}) bulunamadı veya silinemedi.", 'warning');
            }
        } catch (PDOException $e) {
            error_log("Ürün silme hatası: " . $e->getMessage());
            if ($e->getCode() == '23000') { // Foreign key constraint violation
                 mesaj_ayarla('urun_admin_mesaj', "Ürün (ID: {$silinecek_urun_id}) silinemedi. Bu ürün bir veya daha fazla siparişte kullanılıyor olabilir.", 'error');
            } else {
                 mesaj_ayarla('urun_admin_mesaj', "Ürün silinirken bir veritabanı hatası oluştu.", 'error');
            }
        }
    }
    yonlendir('urunler_admin.php');
}

// ----- MEVCUT ÜRÜNLERİ LİSTELEME -----
$urunler_listesi = [];
if (isset($pdo)) {
    try {
        $stmt_urunler_cek = $pdo->query(
            "SELECT u.urun_id, u.urun_adi, k.kategori_adi, u.fiyat, u.stok_miktari, u.resim_yolu
             FROM Urunler u
             LEFT JOIN Kategoriler k ON u.kategori_id = k.kategori_id
             ORDER BY u.urun_id DESC"
        );
        $urunler_listesi = $stmt_urunler_cek->fetchAll();
    } catch (PDOException $e) {
        error_log("Ürün listeleme hatası: " . $e->getMessage());
        mesaj_ayarla('urun_admin_mesaj', "Ürünler yüklenirken bir hata oluştu.", 'error');
    }
}
?>

<h1>Ürün Yönetimi</h1>
<?php mesaj_goster('urun_admin_form_mesaj'); ?>

<div style="background:#fff; padding:20px; border-radius:5px; box-shadow:0 2px 4px rgba(0,0,0,0.1); margin-bottom:30px;">
    <h2><?php echo $duzenleme_modu_urun ? 'Ürünü Düzenle (ID: '.htmlspecialchars($urun_id_duzenle).')' : 'Yeni Ürün Ekle'; ?></h2>
    <form action="urunler_admin.php<?php echo $duzenleme_modu_urun ? '?eylem=duzenle_urun&urun_id='.$urun_id_duzenle : ''; ?>" method="post" enctype="multipart/form-data" novalidate>
        <?php if ($duzenleme_modu_urun && $urun_id_duzenle): ?>
            <input type="hidden" name="urun_id_form_hidden" value="<?php echo htmlspecialchars($urun_id_duzenle); ?>">
            <input type="hidden" name="mevcut_resim_yolu_form" value="<?php echo htmlspecialchars($mevcut_resim_yolu); ?>">
        <?php endif; ?>

        <div>
            <label for="urun_adi_form_id">Ürün Adı <span style="color:red;">*</span></label>
            <input type="text" id="urun_adi_form_id" name="urun_adi_form" value="<?php echo htmlspecialchars($form_urun_adi); ?>" required>
            <?php if (isset($hatalar_urun['urun_adi'])): ?><small class="message error field-error"><?php echo $hatalar_urun['urun_adi']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="kategori_id_form_id">Kategori <span style="color:red;">*</span></label>
            <select id="kategori_id_form_id" name="kategori_id_form" required>
                <option value="">-- Kategori Seçin --</option>
                <?php foreach ($kategoriler_form_icin as $kategori_select): ?>
                    <option value="<?php echo $kategori_select['kategori_id']; ?>" <?php echo ($form_kategori_id == $kategori_select['kategori_id'] ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($kategori_select['kategori_adi']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($hatalar_urun['kategori_id'])): ?><small class="message error field-error"><?php echo $hatalar_urun['kategori_id']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="aciklama_urun_form_id">Açıklama (Opsiyonel)</label>
            <textarea id="aciklama_urun_form_id" name="aciklama_urun_form" rows="4"><?php echo htmlspecialchars($form_aciklama_urun); ?></textarea>
        </div>

        <div style="display:flex; gap: 20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:150px;">
                <label for="fiyat_form_id">Fiyat (TL) <span style="color:red;">*</span></label>
                <input type="number" id="fiyat_form_id" name="fiyat_form" value="<?php echo htmlspecialchars($form_fiyat); ?>" step="0.01" min="0.01" required>
                <?php if (isset($hatalar_urun['fiyat'])): ?><small class="message error field-error"><?php echo $hatalar_urun['fiyat']; ?></small><?php endif; ?>
            </div>
            <div style="flex:1; min-width:150px;">
                <label for="stok_miktari_form_id">Stok Miktarı <span style="color:red;">*</span></label>
                <input type="number" id="stok_miktari_form_id" name="stok_miktari_form" value="<?php echo htmlspecialchars($form_stok_miktari); ?>" min="0" required>
                <?php if (isset($hatalar_urun['stok_miktari'])): ?><small class="message error field-error"><?php echo $hatalar_urun['stok_miktari']; ?></small><?php endif; ?>
            </div>
        </div>

        <div>
            <label for="resim_form_id">Ürün Resmi <?php echo $duzenleme_modu_urun ? '(Değiştirmek için yeni resim seçin)' : '<span style="color:red;">*</span> (Önerilen: JPG, PNG, GIF)'; ?></label>
            <input type="file" id="resim_form_id" name="resim_form" accept="image/jpeg, image/png, image/gif">
            <?php if ($duzenleme_modu_urun && !empty($mevcut_resim_yolu)): ?>
                <p style="margin-top:5px;">
                    <small>Mevcut Resim: <?php echo htmlspecialchars($mevcut_resim_yolu); ?></small><br>
                    <img src="<?php echo URUN_RESIM_HEDEF_KLASOR . htmlspecialchars($mevcut_resim_yolu); ?>" alt="Mevcut Resim" style="max-width:100px; max-height:100px; margin-top:5px; border:1px solid #ccc;">
                </p>
            <?php endif; ?>
            <?php if (isset($hatalar_urun['resim'])): ?><small class="message error field-error"><?php echo $hatalar_urun['resim']; ?></small><?php endif; ?>
        </div>


        <div style="margin-top:20px;">
            <?php if ($duzenleme_modu_urun): ?>
                <button type="submit" name="eylem_urun_guncelle">Ürünü Güncelle</button>
                <a href="urunler_admin.php" style="margin-left:10px; text-decoration:underline;">İptal Et</a>
            <?php else: ?>
                <button type="submit" name="eylem_urun_ekle">Yeni Ürün Ekle</button>
            <?php endif; ?>
        </div>
    </form>
</div>


<h2>Mevcut Ürünler</h2>
<?php mesaj_goster('urun_admin_mesaj'); ?>

<?php if (empty($urunler_listesi) && !isset($pdo)): ?>
     <p class="message error">Veritabanı bağlantısı yok, ürünler listelenemiyor.</p>
<?php elseif (empty($urunler_listesi)): ?>
    <p class="message info">Henüz hiç ürün eklenmemiş.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width:5%;">ID</th>
                <th style="width:10%;">Resim</th>
                <th>Ürün Adı</th>
                <th>Kategori</th>
                <th style="text-align:right;">Fiyat</th>
                <th style="text-align:center;">Stok</th>
                <th style="width:20%;">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($urunler_listesi as $urun_liste): ?>
                <tr>
                    <td><?php echo htmlspecialchars($urun_liste['urun_id']); ?></td>
                    <td>
                        <?php if (!empty($urun_liste['resim_yolu']) && file_exists(URUN_RESIM_HEDEF_KLASOR . $urun_liste['resim_yolu'])): ?>
                            <img src="<?php echo URUN_RESIM_HEDEF_KLASOR . htmlspecialchars($urun_liste['resim_yolu']); ?>" alt="<?php echo htmlspecialchars($urun_liste['urun_adi']); ?>" style="width:60px; height:60px; object-fit:cover; border-radius:4px;">
                        <?php else: ?>
                            <img src="<?php echo URUN_RESIM_HEDEF_KLASOR . '../placeholder.png'; // Placeholder ana images klasöründe ?>" alt="Resim Yok" style="width:60px; height:60px; object-fit:contain; border:1px solid #eee; border-radius:4px;opacity:0.5;">
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($urun_liste['urun_adi']); ?></td>
                    <td><?php echo htmlspecialchars($urun_liste['kategori_adi'] ?? 'Kategorisiz'); ?></td>
                    <td style="text-align:right;"><?php echo number_format($urun_liste['fiyat'], 2, ',', '.'); ?> TL</td>
                    <td style="text-align:center;"><?php echo htmlspecialchars($urun_liste['stok_miktari']); ?></td>
                    <td class="action-links" style="text-align:center;">
                        <a href="urunler_admin.php?eylem=duzenle_urun&urun_id=<?php echo $urun_liste['urun_id']; ?>" class="edit-link">Düzenle</a>
                        <a href="urunler_admin.php?eylem=sil_urun&urun_id=<?php echo $urun_liste['urun_id']; ?>"
                           class="delete-link"
                           onclick="return confirm('\'<?php echo htmlspecialchars(addslashes($urun_liste['urun_adi'])); ?>\' ürününü silmek istediğinizden emin misiniz?');">Sil</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<style>.field-error {display:block;padding:5px;margin-top:2px;margin-bottom:5px;font-size:0.85em;}</style>

<?php
require_once 'includes_admin/footer_admin.php';
?>