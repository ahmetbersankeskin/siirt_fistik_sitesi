<?php
require_once 'includes/header_site.php'; // $pdo, fonksiyonlar ve session için

// 1. Kullanıcı giriş yapmış mı kontrol et
if (!musteri_giris_yapti_mi()) {
    mesaj_ayarla('global_mesaj', 'Ödeme yapabilmek için lütfen giriş yapınız.', 'info');
    $_SESSION['yonlendirme_url_session'] = 'odeme.php'; // Girişten sonra buraya dön
    yonlendir('giris_yap.php');
}

// 2. Sepet boş mu kontrol et
if (!isset($_SESSION['sepet_session']) || empty($_SESSION['sepet_session'])) {
    mesaj_ayarla('global_mesaj', 'Sepetiniz boş. Lütfen ödeme yapmadan önce sepetinize ürün ekleyin.', 'info');
    yonlendir('index.php'); // Ana sayfaya veya sepet sayfasına yönlendirilebilir
}

$musteri_id = get_musteri_id();
$varsayilan_teslimat_adresi = "";
$hatalar_odeme = [];

// Müşterinin kayıtlı adresini varsayılan olarak alalım
if (isset($pdo)) {
    try {
        $stmt_adres = $pdo->prepare("SELECT adres FROM Musteriler WHERE musteri_id = ?");
        $stmt_adres->execute([$musteri_id]);
        $adres_db = $stmt_adres->fetchColumn();
        if ($adres_db) {
            $varsayilan_teslimat_adresi = $adres_db;
        }
    } catch (PDOException $e) {
        error_log("Ödeme sayfası adres çekme hatası: " . $e->getMessage());
        // Adres çekilemezse boş kalır, kullanıcı elle girer.
    }
}

// Form gönderildiğinde siparişi oluşturma işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eylem_siparis_ver'])) {
    $teslimat_adresi_form = veri_temizle($_POST['teslimat_adresi_form']);
    $odeme_yontemi_form = isset($_POST['odeme_yontemi_form']) ? veri_temizle($_POST['odeme_yontemi_form']) : "";

    // Doğrulamalar
    if (empty($teslimat_adresi_form)) {
        $hatalar_odeme['teslimat_adresi'] = "Teslimat adresi boş bırakılamaz.";
    }
    if (empty($odeme_yontemi_form)) {
        $hatalar_odeme['odeme_yontemi'] = "Lütfen bir ödeme yöntemi seçiniz.";
    }

    // Sepetteki ürünlerin stok durumunu son bir kez kontrol et
    $stok_sorunu_var_mi = false;
    if (isset($pdo) && isset($_SESSION['sepet_session']) && !empty($_SESSION['sepet_session'])) {
        foreach ($_SESSION['sepet_session'] as $urun_id_kontrol => $urun_session_kontrol) {
            $stmt_stok_kontrol = $pdo->prepare("SELECT stok_miktari, urun_adi FROM Urunler WHERE urun_id = ?");
            $stmt_stok_kontrol->execute([$urun_id_kontrol]);
            $urun_db_kontrol = $stmt_stok_kontrol->fetch();
            if (!$urun_db_kontrol || $urun_session_kontrol['miktar'] > $urun_db_kontrol['stok_miktari']) {
                $stok_sorunu_var_mi = true;
                mesaj_ayarla(
                    'odeme_mesaj', // Bu mesaj odeme.php'de gösterilecek
                    htmlspecialchars($urun_db_kontrol['urun_adi'] ?? 'Bilinmeyen ürün') .
                    " için stok yetersiz (Maks: " . htmlspecialchars($urun_db_kontrol['stok_miktari'] ?? 0) . "). Lütfen sepetinizi güncelleyin.",
                    'error'
                );
                // Tek bir stok sorunu bile varsa, işlemi durdur ve sepete yönlendir
                yonlendir('sepet.php'); // Bu yönlendirme mesaj_ayarla'dan sonra olmalı
                exit(); // Yönlendirmeden sonra script'i durdurmak önemli
            }
        }
    }


    if (empty($hatalar_odeme) && !$stok_sorunu_var_mi && isset($pdo)) {
        $toplam_tutar_siparis = sepet_toplam_tutar($pdo);

        try {
            $pdo->beginTransaction(); // Veritabanı işlemlerini bir transaction içinde yap

            // 1. Siparisler tablosuna yeni siparişi ekle
            $stmt_siparis_ekle = $pdo->prepare(
                "INSERT INTO Siparisler (musteri_id, toplam_tutar, siparis_durumu, odeme_yontemi, teslimat_adresi, siparis_tarihi)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt_siparis_ekle->execute([$musteri_id, $toplam_tutar_siparis, 'Hazırlanıyor', $odeme_yontemi_form, $teslimat_adresi_form]);
            $yeni_siparis_id = $pdo->lastInsertId();

            // 2. SiparisDetaylari tablosuna sepetteki her bir ürünü ekle ve stokları güncelle
            $stmt_urun_fiyat_al = $pdo->prepare("SELECT fiyat, stok_miktari FROM Urunler WHERE urun_id = ?");
            $stmt_siparis_detay_ekle = $pdo->prepare(
                "INSERT INTO SiparisDetaylari (siparis_id, urun_id, miktar, birim_fiyat)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt_stok_azalt = $pdo->prepare("UPDATE Urunler SET stok_miktari = stok_miktari - ? WHERE urun_id = ?");

            foreach ($_SESSION['sepet_session'] as $urun_id_detay => $urun_bilgisi_detay) {
                $stmt_urun_fiyat_al->execute([$urun_id_detay]);
                $urun_veritabani_bilgisi = $stmt_urun_fiyat_al->fetch();

                $birim_fiyat_o_an = $urun_veritabani_bilgisi['fiyat'];
                $alinan_miktar = $urun_bilgisi_detay['miktar'];

                $stmt_siparis_detay_ekle->execute([$yeni_siparis_id, $urun_id_detay, $alinan_miktar, $birim_fiyat_o_an]);
                $stmt_stok_azalt->execute([$alinan_miktar, $urun_id_detay]);
            }

            $pdo->commit();

            unset($_SESSION['sepet_session']);
            $_SESSION['son_siparis_id_session'] = $yeni_siparis_id;
            yonlendir('siparis_onay.php'); // Mesajı siparis_onay.php'de ayarlayacağız

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Sipariş oluşturma hatası (odeme.php PDOException): " . $e->getMessage());
            mesaj_ayarla('odeme_mesaj', 'Siparişiniz oluşturulurken beklenmedik bir veritabanı sorunu oluştu. Lütfen tekrar deneyin.', 'error');
        } catch (Exception $e) { // Genel Exception yakalama
             $pdo->rollBack();
             error_log("Sipariş oluşturma genel hata (odeme.php Exception): " . $e->getMessage());
             mesaj_ayarla('odeme_mesaj', 'Siparişiniz oluşturulurken bir sorun oluştu: ' . $e->getMessage(), 'error');
        }
    } else {
        // Formda hata varsa, girilen teslimat adresini koru (veya $stok_sorunu_var_mi true ise zaten sepete yönlendirildi)
        if (isset($teslimat_adresi_form) && empty($hatalar_odeme)) { // Sadece form hatası yoksa adresi koru, stok hatası varsa sepete yönlendirir.
            $varsayilan_teslimat_adresi = $teslimat_adresi_form;
        }
    }
}
?>

<div class="ana-container sayfa-icerik">
    <h1>Ödeme Bilgileri</h1>

    <?php mesaj_goster('odeme_mesaj'); ?>

    <form action="odeme.php" method="post" novalidate>
        <div style="display: flex; flex-wrap: wrap; gap: 30px;">

            <!-- Sol Taraf: Adres ve Ödeme Yöntemi -->
            <div style="flex: 2; min-width: 300px;">
                <h2>Teslimat Adresi <span style="color:red;">*</span></h2>
                <textarea id="teslimat_adresi_form_id" name="teslimat_adresi_form" rows="5" placeholder="Lütfen tam teslimat adresinizi giriniz..." required><?php echo htmlspecialchars($varsayilan_teslimat_adresi); ?></textarea>
                <?php if (isset($hatalar_odeme['teslimat_adresi'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar_odeme['teslimat_adresi']; ?></small><?php endif; ?>

                <h2 style="margin-top: 30px;">Ödeme Yöntemi <span style="color:red;">*</span></h2>
                <select id="odeme_yontemi_form_id" name="odeme_yontemi_form" required>
                    <option value="">-- Lütfen Seçiniz --</option>
                    <option value="Kapıda Ödeme" <?php echo (isset($_POST['odeme_yontemi_form']) && $_POST['odeme_yontemi_form'] == 'Kapıda Ödeme') ? 'selected' : ''; ?>>Kapıda Ödeme</option>
                    <option value="Havale/EFT" <?php echo (isset($_POST['odeme_yontemi_form']) && $_POST['odeme_yontemi_form'] == 'Havale/EFT') ? 'selected' : ''; ?>>Banka Havalesi / EFT</option>
                    <option value="Kredi Kartı (Simülasyon)" <?php echo (isset($_POST['odeme_yontemi_form']) && $_POST['odeme_yontemi_form'] == 'Kredi Kartı (Simülasyon)') ? 'selected' : ''; ?>>Kredi Kartı (Simülasyon)</option>
                </select>
                <?php if (isset($hatalar_odeme['odeme_yontemi'])): ?><small class="message error" style="display:block; margin-top:-0.5rem; margin-bottom:0.5rem; padding:5px;"><?php echo $hatalar_odeme['odeme_yontemi']; ?></small><?php endif; ?>
            </div>

            <!-- Sağ Taraf: Sipariş Özeti -->
            <div style="flex: 1; min-width: 280px; background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px;">
                <h2>Sipariş Özeti</h2>
                <?php if (isset($_SESSION['sepet_session']) && !empty($_SESSION['sepet_session']) && isset($pdo)): ?>
                    <ul style="list-style:none; padding:0; margin:0;">
                        <?php
                        $urunler_ozet_map = [];
                        if (!empty(array_keys($_SESSION['sepet_session']))) {
                            $sepet_urun_idler_ozet = array_keys($_SESSION['sepet_session']);
                            $placeholders_ozet = implode(',', array_fill(0, count($sepet_urun_idler_ozet), '?'));

                            $stmt_ozet = $pdo->prepare("SELECT urun_id, urun_adi, fiyat FROM Urunler WHERE urun_id IN ($placeholders_ozet)");
                            $stmt_ozet->execute($sepet_urun_idler_ozet);
                            $urunler_ozet_map = $stmt_ozet->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
                        }

                        foreach ($_SESSION['sepet_session'] as $urun_id_ozet => $urun_bilgisi_ozet):
                            if (!isset($urunler_ozet_map[$urun_id_ozet])) continue;

                            $urun_detay_ozet = $urunler_ozet_map[$urun_id_ozet];
                            $ozet_satir_toplami = $urun_detay_ozet['fiyat'] * $urun_bilgisi_ozet['miktar'];
                        ?>
                            <li style="padding: 8px 0; border-bottom: 1px dashed #ced4da; display:flex; justify-content:space-between; align-items:center;">
                                <span>
                                    <?php echo htmlspecialchars($urun_detay_ozet['urun_adi']); ?>
                                    <small>(<?php echo $urun_bilgisi_ozet['miktar']; ?> adet)</small>
                                </span>
                                <span><?php echo number_format($ozet_satir_toplami, 2, ',', '.'); ?> TL</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <hr style="margin: 15px 0;">
                    <h3 style="display:flex; justify-content:space-between;">
                        <span>Genel Toplam:</span>
                        <span><?php echo number_format(sepet_toplam_tutar($pdo), 2, ',', '.'); ?> TL</span>
                    </h3>
                <?php else: ?>
                    <p>Sipariş özeti için sepette ürün bulunmamaktadır.</p>
                <?php endif; ?>
            </div>

        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button type="submit" name="eylem_siparis_ver" class="checkout-button">Siparişi Onayla ve Tamamla</button>
        </div>
    </form>
</div>

<?php
require_once 'includes/footer_site.php';
?>