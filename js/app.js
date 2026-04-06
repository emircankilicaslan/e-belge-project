/**
 * app.js — Genel Uygulama Fonksiyonları
 * 
 * Bu dosya tüm sayfalarda kullanılan yardımcı fonksiyonları içerir:
 *   - Toast bildirimleri
 *   - Modal yönetimi
 *   - Genel yardımcı araçlar
 */

/* ============================================================
   TOAST BİLDİRİM SİSTEMİ
   Kullanıcıya işlem sonuçlarını bildirir.
   ============================================================ */

/**
 * Ekranın sağ alt köşesinde kısa süreli bildirim gösterir.
 * 
 * @param {string} message  - Gösterilecek mesaj
 * @param {string} type     - 'success' | 'error' | 'info'
 * @param {number} duration - Gösterim süresi (ms), varsayılan 3500
 */
function showToast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    // İkon sözlüğü - her bildirim tipine uygun Font Awesome ikonu
    const icons = {
        success: 'fa-circle-check',
        error:   'fa-circle-xmark',
        info:    'fa-circle-info',
    };

    // Toast elementini oluştur
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `
        <i class="fa-solid ${icons[type] || icons.info}"
           style="color: ${type === 'success' ? '#059669' : type === 'error' ? '#c81e1e' : '#1a56db'}">
        </i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    // Belirtilen süre sonra kaldır
    setTimeout(() => {
        // CSS animasyonu ile kaybolma efekti
        toast.style.transition  = 'opacity .3s, transform .3s';
        toast.style.opacity     = '0';
        toast.style.transform   = 'translateX(100%)';

        // Animasyon bittikten sonra DOM'dan temizle
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/* ============================================================
   MODAL YÖNETİMİ
   ============================================================ */

/**
 * Modalı kapat.
 * Hem modal içindeki kapat butonundan hem de overlay tıklamasından çağrılır.
 */
function closeModal() {
    const modal = document.getElementById('invoiceModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = ''; // Scroll kilidini kaldır
    }
}

/**
 * Modalı aç ve yükleme animasyonunu göster.
 * İçerik invoice.js tarafından doldurulur.
 */
function openModal() {
    const modal = document.getElementById('invoiceModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Arka planın kaydırılmasını engelle
    }
}

// Overlay'e (modal dışına) tıklanınca kapat
document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('invoiceModal');
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            // Sadece overlay'e tıklanınca kapat, modal içine değil
            if (e.target === overlay) closeModal();
        });
    }

    // ESC tuşuyla modalı kapat
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
});

/* ============================================================
   GENEL YARDIMCI FONKSİYONLAR
   ============================================================ */

/**
 * Para birimini Türkçe formatında göster.
 * Örnek: 12500.5 → "12.500,50 ₺"
 * 
 * @param {number} amount - Para miktarı
 * @returns {string}
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style:    'currency',
        currency: 'TRY',
        minimumFractionDigits: 2,
    }).format(amount);
}

/**
 * Tarihi Türkçe formatında göster.
 * Örnek: "2024-11-01" → "01.11.2024"
 * 
 * @param {string} dateStr - ISO tarih stringi
 * @returns {string}
 */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const [year, month, day] = dateStr.split('-');
    return `${day}.${month}.${year}`;
}

/**
 * Belge tipini Türkçe etiketle döndür.
 */
function docTypeLabel(type) {
    const labels = {
        'EFATURA':   'e-Fatura',
        'EARSIV':    'e-Arşiv',
        'EIRSALIYE': 'e-İrsaliye',
        'ESMM':      'e-SMM',
    };
    return labels[type] || type;
}

/**
 * Durum kodunu Türkçe etikete çevir.
 */
function statusLabel(status) {
    const labels = {
        'ACCEPTED': 'Kabul Edildi',
        'REJECTED': 'Reddedildi',
        'WAITING':  'Bekliyor',
        'DRAFT':    'Taslak',
    };
    return labels[status] || status;
}
