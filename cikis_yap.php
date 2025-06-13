<?php
// Bu dosya da doğrudan tarayıcıda görüntülenmez, sadece işlem yapar.
// Sadece fonksiyonlar.php'yi çağırmak yeterli, çünkü o zaten session_start() yapıyor.
require_once 'includes/fonksiyonlar.php';

// Müşteri ile ilgili session değişkenlerini temizle
unset($_SESSION['musteri_id_session']);
unset($_SESSION['musteri_email_session']);
unset($_SESSION['musteri_ad_session']); // Eğer bunu giriş yaparken set ettiyseniz

// İsteğe bağlı: Sepeti de temizlemek isterseniz aşağıdaki satırı aktif edin
// unset($_SESSION['sepet_session']);

// İsteğe bağlı: Oturumu tamamen yok etmek için (tüm session verilerini siler)
// session_destroy();
// Eğer session_destroy() kullanırsanız, bir sonraki sayfada yeni bir session otomatik başlar.

mesaj_ayarla('global_mesaj', 'Başarıyla çıkış yaptınız. Tekrar bekleriz!', 'success');
yonlendir('index.php'); // Ana sayfaya yönlendir
?>