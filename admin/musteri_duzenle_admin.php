<?php
require_once 'includes_admin/header_admin.php'; // $pdo ve fonksiyonlar gelir

$duzenlenecek_musteri_id = null;
$musteri_verileri = null;
$hatalar_duzenleme = [];

// 1. URL'den müşteri ID'sini al
if (isset($_GET['musteri_id']) && is_numeric($_GET['musteri_id'])) {
    $duzenlenecek_musteri_id = intval($_GET['musteri_id']);

    // 2. Form gönderildi mi kontrol et (Güncelleme işlemi)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eylem_musteri_guncelle'])) {
        if (isset($pdo)) {
            $form_ad_duzenle = veri_temizle($_POST['ad_form_duzenle']);
            $form_soyad_duzenle = veri_temizle($_POST['soyad_form_duzenle']);
            $form_email_duzenle = veri_temizle($_POST['email_form_duzenle']);
            $form_telefon_duzenle = veri_temizle($_POST['telefon_form_duzenle'] ?? null);
            $form_adres_duzenle = veri_temizle($_POST['adres_form_duzenle'] ?? null);
            $form_yeni_sifre_duzenle = $_POST['yeni_sifre_form_duzenle']; // Boş olabilir

            // Temel doğrulamalar
            if (empty($form_ad_duzenle)) $hatalar_duzenleme['ad'] = "Ad boş bırakılamaz.";
            if (empty($form_soyad_duzenle)) $hatalar_duzenleme['soyad'] = "Soyad boş bırakılamaz.";
            if (empty($form_email_duzenle)) {
                $hatalar_duzenleme['email'] = "E-posta boş bırakılamaz.";
            } elseif (!filter_var($form_email_duzenle, FILTER_VALIDATE_EMAIL)) {
                $hatalar_duzenleme['email'] = "Geçerli bir e-posta adresi giriniz.";
            } else {
                // E-posta değişmişse ve yeni e-posta başkası tarafından kullanılıyorsa kontrol et
                $stmt_mevcut_email = $pdo->prepare("SELECT email FROM Musteriler WHERE musteri_id = ?");
                $stmt_mevcut_email->execute([$duzenlenecek_musteri_id]);
                $mevcut_email_db = $stmt_mevcut_email->fetchColumn();

                if (strtolower($form_email_duzenle) !== strtolower($mevcut_email_db)) {
                    $stmt_email_var_mi = $pdo->prepare("SELECT musteri_id FROM Musteriler WHERE email = ? AND musteri_id != ?");
                    $stmt_email_var_mi->execute([$form_email_duzenle, $duzenlenecek_musteri_id]);
                    if ($stmt_email_var_mi->fetch()) {
                        $hatalar_duzenleme['email'] = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.";
                    }
                }
            }

            // Yeni şifre girilmişse doğrula
            if (!empty($form_yeni_sifre_duzenle)) {
                if (strlen($form_yeni_sifre_duzenle) < 6) {
                    $hatalar_duzenleme['yeni_sifre'] = "Yeni şifre en az 6 karakter olmalıdır.";
                }
            }

            if (empty($hatalar_duzenleme)) {
                try {
                    // Şifre güncellenecek mi?
                    if (!empty($form_yeni_sifre_duzenle)) {
                        $hashlenmis_yeni_sifre = sifre_hashle($form_yeni_sifre_duzenle);
                        $stmt_guncelle = $pdo->prepare(
                            "UPDATE Musteriler SET ad = ?, soyad = ?, email = ?, telefon = ?, adres = ?, sifre = ?
                             WHERE musteri_id = ?"
                        );
                        $stmt_guncelle->execute([
                            $form_ad_duzenle, $form_soyad_duzenle, $form_email_duzenle,
                            $form_telefon_duzenle, $form_adres_duzenle, $hashlenmis_yeni_sifre,
                            $duzenlenecek_musteri_id
                        ]);
                    } else {
                        // Şifre güncellenmeyecek
                        $stmt_guncelle = $pdo->prepare(
                            "UPDATE Musteriler SET ad = ?, soyad = ?, email = ?, telefon = ?, adres = ?
                             WHERE musteri_id = ?"
                        );
                        $stmt_guncelle->execute([
                            $form_ad_duzenle, $form_soyad_duzenle, $form_email_duzenle,
                            $form_telefon_duzenle, $form_adres_duzenle,
                            $duzenlenecek_musteri_id
                        ]);
                    }
                    mesaj_ayarla('musteri_admin_mesaj', "Müşteri bilgileri (ID: {$duzenlenecek_musteri_id}) başarıyla güncellendi.", 'success');
                    yonlendir('musteriler_admin.php'); // Başarılı güncelleme sonrası listeye dön
                } catch (PDOException $e) {
                    error_log("Müşteri güncelleme hatası: " . $e->getMessage());
                    mesaj_ayarla('musteri_duzenle_mesaj', "Müşteri güncellenirken bir veritabanı hatası oluştu.", 'error');
                }
            } else {
                // Formda hata varsa, hatalı veriyi tekrar forma yansıtmak için
                // $musteri_verileri dizisini formdan gelenlerle güncelleyelim (şifre hariç)
                $musteri_verileri = [
                    'ad' => $form_ad_duzenle,
                    'soyad' => $form_soyad_duzenle,
                    'email' => $form_email_duzenle,
                    'telefon' => $form_telefon_duzenle,
                    'adres' => $form_adres_duzenle
                ];
                 mesaj_ayarla('musteri_duzenle_mesaj', "Lütfen formdaki hataları düzeltin.", 'error');
            }
        } else {
            mesaj_ayarla('musteri_duzenle_mesaj', "Veritabanı bağlantısı kurulamadı.", 'error');
        }
    }

    // 3. Düzenlenecek müşterinin mevcut bilgilerini veritabanından çek (POST yoksa veya hatalı POST sonrası)
    if (isset($pdo) && !$musteri_verileri) { // Eğer POST'tan sonra $musteri_verileri zaten set edilmemişse DB'den çek
        try {
            $stmt_musteri_getir = $pdo->prepare("SELECT * FROM Musteriler WHERE musteri_id = ?");
            $stmt_musteri_getir->execute([$duzenlenecek_musteri_id]);
            $musteri_verileri = $stmt_musteri_getir->fetch();

            if (!$musteri_verileri) {
                mesaj_ayarla('musteri_admin_mesaj', "Düzenlenecek müşteri (ID: {$duzenlenecek_musteri_id}) bulunamadı.", 'error');
                yonlendir('musteriler_admin.php');
            }
        } catch (PDOException $e) {
            error_log("Müşteri çekme hatası (düzenleme): " . $e->getMessage());
            mesaj_ayarla('musteri_admin_mesaj', "Müşteri bilgileri yüklenirken bir hata oluştu.", 'error');
            yonlendir('musteriler_admin.php');
        }
    }

} else {
    // Geçerli bir musteri_id gelmemişse listeye yönlendir
    mesaj_ayarla('musteri_admin_mesaj', 'Düzenlenecek müşteri belirtilmedi.', 'warning');
    yonlendir('musteriler_admin.php');
}
?>

<h1>Müşteri Bilgilerini Düzenle</h1>

<?php mesaj_goster('musteri_duzenle_mesaj'); ?>

<?php if ($musteri_verileri): ?>
    <form action="musteri_duzenle_admin.php?musteri_id=<?php echo htmlspecialchars($duzenlenecek_musteri_id); ?>" method="post" novalidate>
        <div>
            <label for="ad_form_duzenle_id">Ad:</label>
            <input type="text" id="ad_form_duzenle_id" name="ad_form_duzenle" value="<?php echo htmlspecialchars($musteri_verileri['ad'] ?? ''); ?>" required>
            <?php if (isset($hatalar_duzenleme['ad'])): ?><small class="message error" style="display:block;padding:5px;margin-top:2px;"><?php echo $hatalar_duzenleme['ad']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="soyad_form_duzenle_id">Soyad:</label>
            <input type="text" id="soyad_form_duzenle_id" name="soyad_form_duzenle" value="<?php echo htmlspecialchars($musteri_verileri['soyad'] ?? ''); ?>" required>
            <?php if (isset($hatalar_duzenleme['soyad'])): ?><small class="message error" style="display:block;padding:5px;margin-top:2px;"><?php echo $hatalar_duzenleme['soyad']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="email_form_duzenle_id">E-posta:</label>
            <input type="email" id="email_form_duzenle_id" name="email_form_duzenle" value="<?php echo htmlspecialchars($musteri_verileri['email'] ?? ''); ?>" required>
            <?php if (isset($hatalar_duzenleme['email'])): ?><small class="message error" style="display:block;padding:5px;margin-top:2px;"><?php echo $hatalar_duzenleme['email']; ?></small><?php endif; ?>
        </div>

        <div>
            <label for="telefon_form_duzenle_id">Telefon (Opsiyonel):</label>
            <input type="tel" id="telefon_form_duzenle_id" name="telefon_form_duzenle" value="<?php echo htmlspecialchars($musteri_verileri['telefon'] ?? ''); ?>">
        </div>

        <div>
            <label for="adres_form_duzenle_id">Adres (Opsiyonel):</label>
            <textarea id="adres_form_duzenle_id" name="adres_form_duzenle" rows="3"><?php echo htmlspecialchars($musteri_verileri['adres'] ?? ''); ?></textarea>
        </div>

        <hr style="margin: 20px 0;">
        <h4>Şifre Değiştir (Sadece değiştirmek isterseniz doldurun)</h4>
        <div>
            <label for="yeni_sifre_form_duzenle_id">Yeni Şifre:</label>
            <input type="password" id="yeni_sifre_form_duzenle_id" name="yeni_sifre_form_duzenle" placeholder="Boş bırakırsanız şifre değişmez">
            <?php if (isset($hatalar_duzenleme['yeni_sifre'])): ?><small class="message error" style="display:block;padding:5px;margin-top:2px;"><?php echo $hatalar_duzenleme['yeni_sifre']; ?></small><?php endif; ?>
        </div>

        <div style="margin-top:25px;">
            <button type="submit" name="eylem_musteri_guncelle">Bilgileri Güncelle</button>
            <a href="musteriler_admin.php" style="margin-left:15px; text-decoration:underline;">İptal et ve Listeye Dön</a>
        </div>
    </form>
<?php else: ?>
    <p class="message warning">Müşteri bilgileri yüklenemedi veya geçerli bir müşteri seçilmedi.</p>
    <p><a href="musteriler_admin.php" class="checkout-button" style="background-color:#007bff;">Müşteri Listesine Dön</a></p>
<?php endif; ?>


<?php
require_once 'includes_admin/footer_admin.php';
?>