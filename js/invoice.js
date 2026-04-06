/**
 * invoice.js — Fatura İşlemleri
 * 
 * Bu dosya faturalarla ilgili tüm JavaScript işlemlerini yönetir:
 *   1. AJAX ile fatura detayı çekme ve modal'da gösterme
 *   2. PDF indirme simülasyonu
 *   3. XML (UBL-TR) indirme simülasyonu
 *   4. Tablo satırlarına çift tıklama özelliği
 * 
 * Kütüphane: Saf JavaScript (Vanilla JS), fetch API kullanılmıştır.
 * fetch API, modern tarayıcılarda yerleşik olarak gelir;
 * eski tarayıcılar için polyfill veya XMLHttpRequest kullanılabilir.
 */

/* ============================================================
   1. FATURA DETAY MODAL (AJAX)
   ============================================================ */

/**
 * Belirtilen UUID'e ait faturanın detaylarını AJAX ile çeker
 * ve modal pencerede gösterir.
 * 
 * Akış:
 *  1. Modal açılır, yükleme animasyonu gösterilir
 *  2. fetch() ile api/invoice_detail.php'ye GET isteği gönderilir
 *  3. Gelen JSON verisi HTML'e dönüştürülür
 *  4. Modal içeriği güncellenir
 * 
 * @param {string} uuid - Faturanın benzersiz tanımlayıcısı
 */
async function viewInvoice(uuid) {
    // Modalı aç ve yükleme göster
    openModal();

    const modalBody  = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');

    // Yükleme animasyonu göster
    modalBody.innerHTML = `
        <div class="loading-spinner">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <p>Fatura yükleniyor...</p>
        </div>
    `;

    try {
        // AJAX isteği - api/invoice_detail.php endpoint'ine
        const response = await fetch(`api/invoice_detail.php?uuid=${encodeURIComponent(uuid)}`);

        // HTTP hata kontrolü (404, 500 vb.)
        if (!response.ok) {
            throw new Error(`Sunucu hatası: ${response.status}`);
        }

        // Yanıtı JSON olarak parse et
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Bilinmeyen hata');
        }

        // Fatura verisini modal'a render et
        renderInvoiceDetail(data.invoice, modalBody, modalTitle);

    } catch (error) {
        // Hata durumunda kullanıcıya bilgi ver
        console.error('Fatura yüklenirken hata:', error);
        modalBody.innerHTML = `
            <div style="text-align:center; padding:40px; color:#c81e1e;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:32px;"></i>
                <p style="margin-top:12px;">Fatura yüklenemedi.<br>
                   <small style="color:#9ca3af;">${error.message}</small>
                </p>
                <button onclick="closeModal()" 
                        style="margin-top:16px; padding:8px 20px; background:#1a56db; 
                               color:#fff; border:none; border-radius:8px; cursor:pointer;">
                    Kapat
                </button>
            </div>
        `;
        showToast('Fatura detayı yüklenemedi.', 'error');
    }
}

/**
 * Fatura verisini HTML formatında modal'a işler.
 * UBL-TR standardındaki fatura alanlarını kullanıcı dostu şekilde gösterir.
 * 
 * @param {Object} inv         - Fatura nesnesi (API'den gelen)
 * @param {Element} container  - HTML kapsayıcı element
 * @param {Element} titleEl    - Modal başlık elementi
 */
function renderInvoiceDetail(inv, container, titleEl) {
    // Modal başlığını güncelle
    titleEl.textContent = `${docTypeLabel(inv.docType)} Detayı — ${inv.invoiceNumber}`;

    // KDV oranı hesapla (sıfır olabilir, bölme hatasını önle)
    const vatRateDisplay = inv.subtotal > 0
        ? `%${((inv.vatAmount / inv.subtotal) * 100).toFixed(0)}`
        : '%0';

    // Durum rozeti renk sınıfı
    const statusClass = {
        'ACCEPTED': 'status-badge--accepted',
        'REJECTED': 'status-badge--rejected',
        'WAITING' : 'status-badge--waiting',
        'DRAFT'   : 'status-badge--draft',
    }[inv.status] || '';

    // HTML şablonunu oluştur
    container.innerHTML = `
        <!-- Belge başlığı ve durum -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <span class="doc-badge doc-badge--${inv.docType.toLowerCase()}" style="font-size:12px;">
                    ${docTypeLabel(inv.docType)}
                </span>
                <h3 style="font-size:18px; margin-top:6px;">${inv.invoiceNumber}</h3>
            </div>
            <span class="status-badge ${statusClass}" style="font-size:13px;">
                ${statusLabel(inv.status)}
            </span>
        </div>

        <!-- Detay alanları - iki sütunlu ızgara -->
        <div class="detail-grid">

            <div class="detail-item">
                <label>Tarih</label>
                <p>${formatDate(inv.date)}</p>
            </div>

            <div class="detail-item">
                <label>UUID</label>
                <p style="font-family:monospace; font-size:12px; color:#4b5563;">${inv.uuid}</p>
            </div>

            <div class="detail-item">
                <label>Karşı Taraf</label>
                <p style="font-weight:600;">${escapeHtml(inv.partyName)}</p>
            </div>

            <div class="detail-item">
                <label>VKN / TCKN</label>
                <p style="font-family:monospace;">${escapeHtml(inv.partyVkn)}</p>
            </div>

            <div class="detail-item">
                <label>Para Birimi</label>
                <p>${inv.currency || 'TRY'}</p>
            </div>

            <div class="detail-item">
                <label>KDV Oranı</label>
                <p>${vatRateDisplay}</p>
            </div>

        </div>

        <!-- Ayraç -->
        <hr style="border:none; border-top:1px solid #e5e7eb; margin: 16px 0;">

        <!-- Tutar Tablosu -->
        <h4 style="font-size:13px; color:#6b7280; text-transform:uppercase; 
                   letter-spacing:.5px; margin-bottom:8px;">Tutar Özeti</h4>

        <table class="detail-total-table">
            <tr>
                <td style="color:#6b7280;">Matrah (KDV Hariç)</td>
                <td style="text-align:right; font-family:monospace;">
                    ${formatCurrency(inv.subtotal)}
                </td>
            </tr>
            <tr>
                <td style="color:#6b7280;">KDV (${vatRateDisplay})</td>
                <td style="text-align:right; font-family:monospace;">
                    ${formatCurrency(inv.vatAmount)}
                </td>
            </tr>
            <tr style="background:#f0f7ff;">
                <td><strong>Genel Toplam</strong></td>
                <td style="text-align:right; font-family:monospace;">
                    <strong>${formatCurrency(inv.total)}</strong>
                </td>
            </tr>
        </table>

        <!-- Alt İşlem Butonları -->
        <div style="display:flex; gap:10px; margin-top:24px; flex-wrap:wrap;">
            <button onclick="downloadPDF('${inv.uuid}')"
                    class="btn btn--secondary" style="flex:1; justify-content:center;">
                <i class="fa-regular fa-file-pdf"></i> PDF İndir
            </button>
            <button onclick="downloadXML('${inv.uuid}')"
                    class="btn btn--secondary" style="flex:1; justify-content:center;">
                <i class="fa-solid fa-code"></i> UBL-TR XML
            </button>
            <button onclick="closeModal()"
                    class="btn btn--primary" style="flex:1; justify-content:center;">
                <i class="fa-solid fa-xmark"></i> Kapat
            </button>
        </div>
    `;
}

/* ============================================================
   2. PDF İNDİRME
   ============================================================ */

/**
 * Faturanın PDF versiyonunu indirir.
 * 
 * Gerçek uygulamada:
 *  - Sunucu tarafında TCPDF veya FPDF ile PDF üretilir
 *  - GİB'in onaylı PDF şablonu kullanılır (e-Fatura görsel formatı)
 *  - Content-Disposition: attachment header'ı ile indirilir
 * 
 * @param {string} uuid - Fatura UUID'i
 */
function downloadPDF(uuid) {
    showToast('PDF hazırlanıyor, lütfen bekleyin...', 'info', 2000);

    // Gerçek uygulamada: window.location.href = `api/download_pdf.php?uuid=${uuid}`;
    // Demo: 1.5 saniye simülasyon
    setTimeout(() => {
        showToast('PDF indirildi: ' + uuid + '.pdf', 'success');
    }, 1500);
}

/* ============================================================
   3. XML (UBL-TR) İNDİRME
   ============================================================ */

/**
 * Faturanın UBL-TR formatındaki XML dosyasını indirir.
 * 
 * UBL-TR (Universal Business Language - Türkiye):
 *  - GİB tarafından zorunlu tutulan XML standardıdır
 *  - ISO/IEC 19845 standardına dayanır
 *  - e-Fatura, e-Arşiv, e-İrsaliye için kullanılır
 * 
 * @param {string} uuid - Fatura UUID'i
 */
function downloadXML(uuid) {
    showToast('UBL-TR XML hazırlanıyor...', 'info', 2000);

    // Gerçek uygulamada: window.location.href = `api/download_xml.php?uuid=${uuid}`;
    setTimeout(() => {
        showToast('XML indirildi: ' + uuid + '.xml', 'success');
    }, 1500);
}

/* ============================================================
   4. TABLO SATIRI ÇIFT TIKLAMA
   ============================================================ */

/**
 * Sayfa yüklendikten sonra tablo satırlarına çift tıklama ekle.
 * Kullanıcı bir satıra çift tıkladığında fatura detayı açılır.
 */
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('.invoice-row');

    rows.forEach(function (row) {
        row.addEventListener('dblclick', function () {
            // data-id attribute'undan UUID'i al
            const uuid = row.getAttribute('data-id');
            if (uuid) viewInvoice(uuid);
        });

        // Çift tıklanabilir olduğunu gösteren cursor stilini ekle
        row.style.cursor = 'pointer';
    });

    // Kaç fatura gösterildiğini konsola yaz (debug amaçlı)
    console.info(`[e-Belge] Tabloda ${rows.length} fatura gösterildi.`);
});

/* ============================================================
   5. YARDIMCI: HTML ESCAPE
   XSS saldırılarını önlemek için kullanıcı verisini güvenli hale getir
   ============================================================ */

/**
 * Özel HTML karakterlerini kaçış kodlarına çevirir.
 * Örnek: <script> → &lt;script&gt;
 * 
 * @param {string} str - Ham metin
 * @returns {string}   - Güvenli HTML
 */
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
