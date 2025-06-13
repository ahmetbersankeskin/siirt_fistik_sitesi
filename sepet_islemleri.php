<?php
// Bu dosya doğrudan tarayıcıda görüntülenmeyecek, formlardan POST isteği alacak.
// Bu yüzden header_site.php veya footer_site.php çağırmıyoruz.
// Sadece veritabanı bağlantısı ve fonksiyonlar yeterli.

require_once 'includes/db_baglanti.php';   // $pdo için
require_once 'includes/fonksiyonlar.php'; // session_start(), mesaj_ayarla(), yonlendir() vb. için

// Gelen isteğin POST olup olmadığını kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Hangi eylemin istendiğini kontrol et
    // Eylem 1: Sepete Ürün Ekleme
    if (isset($_POST['eylem_sepete_ekle']) && isset($_POST['urun_id_form'])) {
        $urun_id = intval($_POST['urun_id_form']);
        $miktar = isset($_POST['miktar_form']) ? intval($_POST['miktar_form']) : 1;

        if ($miktar <= 0) {
            mesaj_ayarla('sepet_mesaj', 'Geçersiz miktar girdiniz.', 'error');
            yonlendir($_SERVER['HTTP_REFERER'] ?? 'index.php'); // Geldiği sayfaya geri dön
        }

        // Ürünün varlığını ve stok durumunu kontrol et
        if (isset($pdo)) {
            try {
                $stmt_urun_kontrol = $pdo->prepare("SELECT urun_adi, stok_miktari FROM Urunler WHERE urun_id = ?");
                $stmt_urun_kontrol->execute([$urun_id]);
                $urun_db = $stmt_urun_kontrol->fetch();

                if (!$urun_db) {
                    mesaj_ayarla('sepet_mesaj', 'Eklenmek istenen ürün bulunamadı.', 'error');
                } elseif ($urun_db['stok_miktari'] <= 0) {
                    mesaj_ayarla('sepet_mesaj', htmlspecialchars($urun_db['urun_adi']) . ' adlı ürün stokta kalmamıştır.', 'warning');
                } elseif ($miktar > $urun_db['stok_miktari']) {
                    mesaj_ayarla('sepet_mesaj', htmlspecialchars($urun_db['urun_adi']) . ' için stokta yeterli ürün yok. En fazla ' . $urun_db['stok_miktari'] . ' adet ekleyebilirsiniz.', 'warning');
                } else {
                    // Ürün sepete eklenebilir
                    if (!isset($_SESSION['sepet_session'])) {
                        $_SESSION['sepet_session'] = []; // Eğer sepet session'ı yoksa oluştur
                    }

                    if (isset($_SESSION['sepet_session'][$urun_id])) {
                        // Ürün zaten sepetteyse, miktarını artır (stok kontrolü ile)
                        $yeni_toplam_miktar = $_SESSION['sepet_session'][$urun_id]['miktar'] + $miktar;
                        if ($yeni_toplam_miktar > $urun_db['stok_miktari']) {
                             mesaj_ayarla('sepet_mesaj', htmlspecialchars($urun_db['urun_adi']) . ' için sepetteki ve eklenen miktar toplamı stoğu aşıyor. En fazla ' . ($urun_db['stok_miktari'] - $_SESSION['sepet_session'][$urun_id]['miktar']) . ' adet daha ekleyebilirsiniz.', 'warning');
                        } else {
                            $_SESSION['sepet_session'][$urun_id]['miktar'] = $yeni_toplam_miktar;
                            mesaj_ayarla('global_mesaj', htmlspecialchars($urun_db['urun_adi']) . ' sepete eklendi (miktar güncellendi).', 'success');
                        }
                    } else {
                        // Ürün sepette yoksa, yeni olarak ekle
                        $_SESSION['sepet_session'][$urun_id] = ['miktar' => $miktar];
                        mesaj_ayarla('global_mesaj', htmlspecialchars($urun_db['urun_adi']) . ' sepete eklendi.', 'success');
                    }
                }
            } catch (PDOException $e) {
                error_log("Sepete ekleme ürün kontrol hatası: " . $e->getMessage());
                mesaj_ayarla('sepet_mesaj', 'Sepete ekleme sırasında bir veritabanı sorunu oluştu.', 'error');
            }
        } else {
            mesaj_ayarla('sepet_mesaj', 'Veritabanı bağlantısı kurulamadı.', 'error');
        }
        yonlendir($_SERVER['HTTP_REFERER'] ?? 'index.php'); // Geldiği sayfaya geri dön
    }

    // Eylem 2: Sepetteki Ürün Miktarlarını Güncelleme (sepet.php'den gelecek)
    elseif (isset($_POST['eylem_sepeti_guncelle']) && isset($_POST['miktarlar_form'])) {
        $guncellenecek_miktarlar = $_POST['miktarlar_form'];
        $guncelleme_yapildi = false;

        if (isset($pdo) && is_array($guncellenecek_miktarlar) && !empty($_SESSION['sepet_session'])) {
            foreach ($guncellenecek_miktarlar as $urun_id => $yeni_miktar) {
                $urun_id = intval($urun_id);
                $yeni_miktar = intval($yeni_miktar);

                if (isset($_SESSION['sepet_session'][$urun_id])) {
                    if ($yeni_miktar > 0) {
                        // Stok kontrolü
                        $stmt_stok_guncelle = $pdo->prepare("SELECT stok_miktari, urun_adi FROM Urunler WHERE urun_id = ?");
                        $stmt_stok_guncelle->execute([$urun_id]);
                        $urun_db_guncelle = $stmt_stok_guncelle->fetch();

                        if ($urun_db_guncelle && $yeni_miktar <= $urun_db_guncelle['stok_miktari']) {
                            $_SESSION['sepet_session'][$urun_id]['miktar'] = $yeni_miktar;
                            $guncelleme_yapildi = true;
                        } elseif ($urun_db_guncelle) {
                            // Stoktan fazla istenirse, mevcut stoğa ayarla
                            $_SESSION['sepet_session'][$urun_id]['miktar'] = $urun_db_guncelle['stok_miktari'];
                            mesaj_ayarla('sepet_mesaj', htmlspecialchars($urun_db_guncelle['urun_adi']) . ' için stokta yeterli ürün yok. Miktar ' . $urun_db_guncelle['stok_miktari'] . ' olarak ayarlandı.', 'warning');
                            $guncelleme_yapildi = true;
                        }
                    } else {
                        // Miktar 0 veya daha az ise ürünü sepetten kaldır
                        unset($_SESSION['sepet_session'][$urun_id]);
                        $guncelleme_yapildi = true;
                    }
                }
            }
        }
        if ($guncelleme_yapildi) {
            mesaj_ayarla('sepet_mesaj', 'Sepetiniz güncellendi.', 'success');
        }
        yonlendir('sepet.php'); // Sepet sayfasına yönlendir
    }

    // Diğer sepet eylemleri (örn: tek bir ürünü silmek için POST) buraya eklenebilir.

}
// Eylem 3: GET ile Sepetten Ürün Silme (sepet.php'deki X linkinden gelecek)
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['eylem']) && $_GET['eylem'] == 'urunu_kaldir' && isset($_GET['urun_id'])) {
    $urun_id_kaldir = intval($_GET['urun_id']);
    if (isset($_SESSION['sepet_session'][$urun_id_kaldir])) {
        unset($_SESSION['sepet_session'][$urun_id_kaldir]);
        mesaj_ayarla('sepet_mesaj', 'Ürün sepetten kaldırıldı.', 'success');
    }
    yonlendir('sepet.php'); // Sepet sayfasına yönlendir
}
else {
    // Geçersiz istek veya doğrudan erişim denemesi
    mesaj_ayarla('global_mesaj', 'Geçersiz işlem.', 'error');
    yonlendir('index.php'); // Ana sayfaya yönlendir
}
?>