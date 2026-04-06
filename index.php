<?php



ini_set('display_errors', 1);
error_reporting(E_ALL);


session_start();


require_once 'api/InvoiceAPI.php';
require_once 'api/User.php';


$currentUser = new User(
    id: 1,
    name: 'Ahmet Yılmaz',
    vkn: '1234567890',         // Vergi Kimlik Numarası
    companyName: 'Yılmaz Ticaret A.Ş.',
    taxOffice: 'Kadıköy VD'
);

// InvoiceAPI sınıfı ile fatura verilerini çek
$invoiceAPI = new InvoiceAPI();

// GET parametresine göre fatura tipini belirle (varsayılan: 'sales' = satış)
$activeTab = $_GET['tab'] ?? 'sales';
$activeTab = in_array($activeTab, ['sales', 'purchase']) ? $activeTab : 'sales';


$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Filtre parametrelerini al
$filters = [
    'startDate'   => $_GET['startDate']   ?? '',
    'endDate'     => $_GET['endDate']     ?? '',
    'status'      => $_GET['status']      ?? '',
    'docType'     => $_GET['docType']     ?? '',
    'searchQuery' => $_GET['searchQuery'] ?? '',
];

// Fatura listesini getir
if ($activeTab === 'sales') {
    $invoices = $invoiceAPI->getSalesInvoices($currentUser->vkn, $filters, $perPage, $offset);
    $total    = $invoiceAPI->getSalesCount($currentUser->vkn, $filters);
} else {
    $invoices = $invoiceAPI->getPurchaseInvoices($currentUser->vkn, $filters, $perPage, $offset);
    $total    = $invoiceAPI->getPurchaseCount($currentUser->vkn, $filters);
}

// Özet istatistikleri hesapla
$summary = $invoiceAPI->getSummary($currentUser->vkn);

// Toplam sayfa sayısını hesapla
$totalPages = (int)ceil($total / $perPage);

/**
 * Para birimini Türk Lirası formatında göster
 * Örnek: 12500.50 → "12.500,50 ₺"
 */
function formatCurrency(float $amount): string {
    return number_format($amount, 2, ',', '.') . ' ₺';
}

/**
 * Tarihi Türkçe formatında göster
 * Örnek: "2024-03-15" → "15.03.2024"
 */
function formatDate(string $date): string {
    return date('d.m.Y', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>e-Belge Sistemi | <?= htmlspecialchars($currentUser->companyName) ?></title>

    <!-- Ana stil dosyası -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Google Fonts - İkon kütüphanesi -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- ============================================================
     ÜST MENÜ / NAVİGASYON
     ============================================================ -->
<header class="topbar">
    <div class="topbar-brand">
        <i class="fa-solid fa-file-invoice"></i>
        <span>e-Belge Sistemi</span>
    </div>
    <div class="topbar-user">
        <i class="fa-regular fa-building"></i>
        <div>
            <strong><?= htmlspecialchars($currentUser->companyName) ?></strong>
            <small>VKN: <?= htmlspecialchars($currentUser->vkn) ?></small>
        </div>
        <a href="logout.php" class="btn-logout" title="Çıkış Yap">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</header>

<!-- ============================================================
     ANA KAPSAYICI
     ============================================================ -->
<div class="container">

    <!-- ---- ÖZET KARTLARI ---- -->
    <section class="summary-cards">

        <!-- Toplam Satış Kartı -->
        <div class="card card--green">
            <div class="card-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
            <div class="card-info">
                <span class="card-label">Bu Ay Satış</span>
                <span class="card-value"><?= formatCurrency($summary['monthlySales']) ?></span>
                <span class="card-sub"><?= $summary['monthlySalesCount'] ?> fatura</span>
            </div>
        </div>

        <!-- Toplam Alış Kartı -->
        <div class="card card--blue">
            <div class="card-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
            <div class="card-info">
                <span class="card-label">Bu Ay Alış</span>
                <span class="card-value"><?= formatCurrency($summary['monthlyPurchase']) ?></span>
                <span class="card-sub"><?= $summary['monthlyPurchaseCount'] ?> fatura</span>
            </div>
        </div>

        <!-- Bekleyen Faturalar -->
        <div class="card card--orange">
            <div class="card-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="card-info">
                <span class="card-label">Bekleyen</span>
                <span class="card-value"><?= $summary['pendingCount'] ?></span>
                <span class="card-sub">İşlem bekliyor</span>
            </div>
        </div>

        <!-- KDV Bakiyesi -->
        <div class="card card--purple">
            <div class="card-icon"><i class="fa-solid fa-percent"></i></div>
            <div class="card-info">
                <span class="card-label">KDV Bakiye</span>
                <span class="card-value"><?= formatCurrency($summary['vatBalance']) ?></span>
                <span class="card-sub">Devreden KDV</span>
            </div>
        </div>

    </section>

    <!-- ---- SEKME BAŞLIKLARI (Satış / Alış) ---- -->
    <div class="tab-header">
        <a href="?tab=sales"
           class="tab-btn <?= $activeTab === 'sales' ? 'tab-btn--active' : '' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            Satış Faturaları
            <span class="badge badge--green"><?= $invoiceAPI->getSalesCount($currentUser->vkn, []) ?></span>
        </a>
        <a href="?tab=purchase"
           class="tab-btn <?= $activeTab === 'purchase' ? 'tab-btn--active' : '' ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            Alış Faturaları
            <span class="badge badge--blue"><?= $invoiceAPI->getPurchaseCount($currentUser->vkn, []) ?></span>
        </a>
    </div>

    <!-- ---- FİLTRE FORMU ---- -->
    <form class="filter-bar" method="GET" id="filterForm">
        <!-- Aktif sekmeyi korumak için gizli alan -->
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

        <div class="filter-group">
            <label>Başlangıç Tarihi</label>
            <input type="date" name="startDate"
                   value="<?= htmlspecialchars($filters['startDate']) ?>">
        </div>

        <div class="filter-group">
            <label>Bitiş Tarihi</label>
            <input type="date" name="endDate"
                   value="<?= htmlspecialchars($filters['endDate']) ?>">
        </div>

        <div class="filter-group">
            <label>Belge Tipi</label>
            <select name="docType">
                <option value="">Tümü</option>
                <option value="EFATURA"  <?= $filters['docType']==='EFATURA'  ? 'selected':'' ?>>e-Fatura</option>
                <option value="EARSIV"   <?= $filters['docType']==='EARSIV'   ? 'selected':'' ?>>e-Arşiv</option>
                <option value="EIRSALIYE"<?= $filters['docType']==='EIRSALIYE'? 'selected':'' ?>>e-İrsaliye</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Durum</label>
            <select name="status">
                <option value="">Tümü</option>
                <option value="ACCEPTED" <?= $filters['status']==='ACCEPTED' ? 'selected':'' ?>>Kabul Edildi</option>
                <option value="REJECTED" <?= $filters['status']==='REJECTED' ? 'selected':'' ?>>Reddedildi</option>
                <option value="WAITING"  <?= $filters['status']==='WAITING'  ? 'selected':'' ?>>Bekliyor</option>
            </select>
        </div>

        <div class="filter-group filter-group--search">
            <label>Arama</label>
            <input type="text" name="searchQuery"
                   placeholder="Fatura no veya firma adı..."
                   value="<?= htmlspecialchars($filters['searchQuery']) ?>">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn--primary">
                <i class="fa-solid fa-magnifying-glass"></i> Filtrele
            </button>
            <a href="?tab=<?= $activeTab ?>" class="btn btn--secondary">
                <i class="fa-solid fa-xmark"></i> Temizle
            </a>
        </div>
    </form>

    <!-- ---- FATURA TABLOSU ---- -->
    <div class="table-wrapper">
        <table class="invoice-table" id="invoiceTable">
            <thead>
                <tr>
                    <th>Belge Tipi</th>
                    <th>Fatura No</th>
                    <th>
                        <?= $activeTab === 'sales' ? 'Alıcı' : 'Satıcı' ?>
                    </th>
                    <th>VKN/TCKN</th>
                    <th>Tarih</th>
                    <th class="text-right">Matrah</th>
                    <th class="text-right">KDV</th>
                    <th class="text-right">Toplam</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <!-- Veri yoksa kullanıcıya bilgi göster -->
                    <tr>
                        <td colspan="10" class="empty-row">
                            <i class="fa-regular fa-folder-open"></i>
                            <p>Bu kriterlere uygun fatura bulunamadı.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr class="invoice-row"
                            data-id="<?= htmlspecialchars($inv['uuid']) ?>"
                            data-status="<?= htmlspecialchars($inv['status']) ?>">

                            <!-- Belge Tipi Rozeti -->
                            <td>
                                <span class="doc-badge doc-badge--<?= strtolower($inv['docType']) ?>">
                                    <?= htmlspecialchars($inv['docType']) ?>
                                </span>
                            </td>

                            <!-- Fatura Numarası -->
                            <td class="invoice-no">
                                <?= htmlspecialchars($inv['invoiceNumber']) ?>
                            </td>

                            <!-- Karşı Taraf Adı -->
                            <td class="party-name">
                                <?= htmlspecialchars($inv['partyName']) ?>
                            </td>

                            <!-- VKN / TCKN -->
                            <td class="mono">
                                <?= htmlspecialchars($inv['partyVkn']) ?>
                            </td>

                            <!-- Fatura Tarihi -->
                            <td><?= formatDate($inv['date']) ?></td>

                            <!-- Matrah (KDV hariç tutar) -->
                            <td class="text-right mono">
                                <?= formatCurrency($inv['subtotal']) ?>
                            </td>

                            <!-- KDV Tutarı -->
                            <td class="text-right mono">
                                <?= formatCurrency($inv['vatAmount']) ?>
                            </td>

                            <!-- Genel Toplam -->
                            <td class="text-right mono fw-bold">
                                <?= formatCurrency($inv['total']) ?>
                            </td>

                            <!-- Durum Rozeti -->
                            <td>
                                <span class="status-badge status-badge--<?= strtolower($inv['status']) ?>">
                                    <?php
                                    // Durum kodunu Türkçe'ye çevir
                                    $statusLabels = [
                                        'ACCEPTED' => 'Kabul',
                                        'REJECTED' => 'Red',
                                        'WAITING'  => 'Bekliyor',
                                        'DRAFT'    => 'Taslak',
                                    ];
                                    echo $statusLabels[$inv['status']] ?? $inv['status'];
                                    ?>
                                </span>
                            </td>

                            <!-- İşlem Butonları -->
                            <td class="actions">
                                <!-- Detay görüntüle - AJAX ile açılır -->
                                <button class="icon-btn"
                                        onclick="viewInvoice('<?= htmlspecialchars($inv['uuid']) ?>')"
                                        title="Detay Görüntüle">
                                    <i class="fa-regular fa-eye"></i>
                                </button>

                                <!-- PDF indir -->
                                <button class="icon-btn"
                                        onclick="downloadPDF('<?= htmlspecialchars($inv['uuid']) ?>')"
                                        title="PDF İndir">
                                    <i class="fa-regular fa-file-pdf"></i>
                                </button>

                                <!-- UBL-TR XML indir -->
                                <button class="icon-btn"
                                        onclick="downloadXML('<?= htmlspecialchars($inv['uuid']) ?>')"
                                        title="XML İndir">
                                    <i class="fa-solid fa-code"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

            <!-- Tablo alt toplamları -->
            <?php if (!empty($invoices)): ?>
                <?php
                // Sayfadaki faturaların toplamlarını hesapla
                $pageSubtotal  = array_sum(array_column($invoices, 'subtotal'));
                $pageVat       = array_sum(array_column($invoices, 'vatAmount'));
                $pageTotal     = array_sum(array_column($invoices, 'total'));
                ?>
                <tfoot>
                    <tr class="table-total">
                        <td colspan="5">
                            Sayfa Toplamı (<?= count($invoices) ?> fatura)
                        </td>
                        <td class="text-right mono"><?= formatCurrency($pageSubtotal) ?></td>
                        <td class="text-right mono"><?= formatCurrency($pageVat) ?></td>
                        <td class="text-right mono fw-bold"><?= formatCurrency($pageTotal) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- ---- SAYFALAMA ---- -->
    <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?tab=<?= $activeTab ?>&page=<?= $page-1 ?>&<?= http_build_query(array_filter($filters)) ?>"
                   class="page-btn">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php
            // Mevcut sayfanın etrafında 2 sayfa göster
            $startPage = max(1, $page - 2);
            $endPage   = min($totalPages, $page + 2);

            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>"
                   class="page-btn <?= $i === $page ? 'page-btn--active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?tab=<?= $activeTab ?>&page=<?= $page+1 ?>&<?= http_build_query(array_filter($filters)) ?>"
                   class="page-btn">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            <?php endif; ?>

            <span class="page-info">
                Toplam <?= number_format($total) ?> kayıt, <?= $totalPages ?> sayfa
            </span>
        </nav>
    <?php endif; ?>

</div><!-- /.container -->

<!-- ============================================================
     FATURA DETAY MODAL PENCERESI
     JavaScript tarafından doldurulur (api/invoice_detail.php)
     ============================================================ -->
<div class="modal-overlay" id="invoiceModal" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">Fatura Detayı</h2>
            <button class="modal-close" onclick="closeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- AJAX ile doldurulur -->
            <div class="loading-spinner">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Yükleniyor...</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     TOAST BİLDİRİMLERİ
     JavaScript tarafından yönetilir
     ============================================================ -->
<div id="toastContainer" class="toast-container"></div>

<!-- JavaScript dosyaları -->
<script src="js/app.js"></script>
<script src="js/invoice.js"></script>

</body>
</html>
