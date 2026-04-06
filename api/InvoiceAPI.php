<?php
/**
 * InvoiceAPI Sınıfı
 * 
 * GİB özel entegratör API'si ile iletişimi simüle eder.
 * Gerçek uygulamada bu sınıf:
 *   - GİB Entegrasyon API'sine SOAP/REST istekleri gönderir
 *   - UBL-TR formatındaki XML belgelerini parse eder
 *   - Veritabanına kaydeder ve önbellekler
 * 
 * GİB API Dokümantasyonu: https://ebelge.gib.gov.tr/
 */

class InvoiceAPI
{
    /**
     * Demo amaçlı sabit fatura verileri.
     * Üretim ortamında bu veriler GİB entegrasyon servisinden gelir.
     * 
     * Gerçek uygulama akışı:
     *  1. Servis çağrısı: getEInvoiceList(vkn, baslangicTarihi, bitisTarihi)
     *  2. Dönen UBL-TR XML parse edilir
     *  3. Veritabanına yazılır (önbellek)
     *  4. Veriler arayüze iletilir
     */
    private array $demoSales = [
        [
            'uuid'          => 'INV-2024-S001',
            'invoiceNumber' => 'YLM2024000001',
            'partyName'     => 'Teknoloji A.Ş.',
            'partyVkn'      => '9876543210',
            'date'          => '2024-11-01',
            'docType'       => 'EFATURA',
            'subtotal'      => 50000.00,
            'vatRate'       => 20,
            'vatAmount'     => 10000.00,
            'total'         => 60000.00,
            'status'        => 'ACCEPTED',
            'currency'      => 'TRY',
        ],
        [
            'uuid'          => 'INV-2024-S002',
            'invoiceNumber' => 'YLM2024000002',
            'partyName'     => 'Müşteri Ltd. Şti.',
            'partyVkn'      => '11122233344',   // TCKN (bireysel)
            'date'          => '2024-11-05',
            'docType'       => 'EARSIV',
            'subtotal'      => 8500.00,
            'vatRate'       => 20,
            'vatAmount'     => 1700.00,
            'total'         => 10200.00,
            'status'        => 'ACCEPTED',
            'currency'      => 'TRY',
        ],
        [
            'uuid'          => 'INV-2024-S003',
            'invoiceNumber' => 'YLM2024000003',
            'partyName'     => 'Global Export GmbH',
            'partyVkn'      => 'DE123456789',
            'date'          => '2024-11-10',
            'docType'       => 'EFATURA',
            'subtotal'      => 120000.00,
            'vatRate'       => 0,              // İhracat faturası - KDV muaf
            'vatAmount'     => 0.00,
            'total'         => 120000.00,
            'status'        => 'ACCEPTED',
            'currency'      => 'EUR',
        ],
        [
            'uuid'          => 'INV-2024-S004',
            'invoiceNumber' => 'YLM2024000004',
            'partyName'     => 'Perakende Müşteri',
            'partyVkn'      => '55566677788',
            'date'          => '2024-11-15',
            'docType'       => 'EARSIV',
            'subtotal'      => 3200.00,
            'vatRate'       => 10,
            'vatAmount'     => 320.00,
            'total'         => 3520.00,
            'status'        => 'WAITING',
            'currency'      => 'TRY',
        ],
        [
            'uuid'          => 'INV-2024-S005',
            'invoiceNumber' => 'YLM2024000005',
            'partyName'     => 'Devlet Kurumu Md.',
            'partyVkn'      => '2345678901',
            'date'          => '2024-11-20',
            'docType'       => 'EFATURA',
            'subtotal'      => 75000.00,
            'vatRate'       => 20,
            'vatAmount'     => 15000.00,
            'total'         => 90000.00,
            'status'        => 'REJECTED',
            'currency'      => 'TRY',
        ],
    ];

    private array $demoPurchases = [
        [
            'uuid'          => 'INV-2024-P001',
            'invoiceNumber' => 'SUP2024100001',
            'partyName'     => 'Tedarikçi A.Ş.',
            'partyVkn'      => '4567890123',
            'date'          => '2024-11-02',
            'docType'       => 'EFATURA',
            'subtotal'      => 35000.00,
            'vatRate'       => 20,
            'vatAmount'     => 7000.00,
            'total'         => 42000.00,
            'status'        => 'ACCEPTED',
            'currency'      => 'TRY',
        ],
        [
            'uuid'          => 'INV-2024-P002',
            'invoiceNumber' => 'SUP2024100002',
            'partyName'     => 'Ofis Malzeme Ltd.',
            'partyVkn'      => '3456789012',
            'date'          => '2024-11-07',
            'docType'       => 'EARSIV',
            'subtotal'      => 4200.00,
            'vatRate'       => 20,
            'vatAmount'     => 840.00,
            'total'         => 5040.00,
            'status'        => 'ACCEPTED',
            'currency'      => 'TRY',
        ],
        [
            'uuid'          => 'INV-2024-P003',
            'invoiceNumber' => 'SUP2024100003',
            'partyName'     => 'Kargo Hizmetleri',
            'partyVkn'      => '6789012345',
            'date'          => '2024-11-12',
            'docType'       => 'EIRSALIYE',
            'subtotal'      => 1800.00,
            'vatRate'       => 20,
            'vatAmount'     => 360.00,
            'total'         => 2160.00,
            'status'        => 'ACCEPTED',
            'currency'      => 'TRY',
        ],
        [
            'uuid'          => 'INV-2024-P004',
            'invoiceNumber' => 'SUP2024100004',
            'partyName'     => 'Yazılım Hizmetleri A.Ş.',
            'partyVkn'      => '7890123456',
            'date'          => '2024-11-18',
            'docType'       => 'EFATURA',
            'subtotal'      => 25000.00,
            'vatRate'       => 20,
            'vatAmount'     => 5000.00,
            'total'         => 30000.00,
            'status'        => 'WAITING',
            'currency'      => 'TRY',
        ],
    ];

    /**
     * Satış faturalarını getir.
     * Filtreleme ve sayfalama destekler.
     *
     * @param string $vkn     Mükellef VKN
     * @param array  $filters Filtre parametreleri (tarih, durum, tip vb.)
     * @param int    $limit   Sayfa başına kayıt sayısı
     * @param int    $offset  Başlangıç noktası (sayfalama)
     * @return array
     */
    public function getSalesInvoices(string $vkn, array $filters, int $limit = 10, int $offset = 0): array
    {
        return $this->applyFiltersAndPaginate($this->demoSales, $filters, $limit, $offset);
    }

    /**
     * Alış faturalarını getir.
     *
     * @param string $vkn     Mükellef VKN
     * @param array  $filters Filtre parametreleri
     * @param int    $limit   Sayfa başına kayıt
     * @param int    $offset  Sayfalama başlangıcı
     * @return array
     */
    public function getPurchaseInvoices(string $vkn, array $filters, int $limit = 10, int $offset = 0): array
    {
        return $this->applyFiltersAndPaginate($this->demoPurchases, $filters, $limit, $offset);
    }

    /**
     * Satış faturalarının toplam sayısını döndür.
     */
    public function getSalesCount(string $vkn, array $filters): int
    {
        return count($this->applyFilters($this->demoSales, $filters));
    }

    /**
     * Alış faturalarının toplam sayısını döndür.
     */
    public function getPurchaseCount(string $vkn, array $filters): int
    {
        return count($this->applyFilters($this->demoPurchases, $filters));
    }

    /**
     * UUID'e göre tek bir fatura detayını getir.
     * Modal pencerede göstermek için kullanılır.
     */
    public function getInvoiceByUUID(string $uuid): ?array
    {
        // Tüm faturalar arasında UUID ile ara
        $allInvoices = array_merge($this->demoSales, $this->demoPurchases);
        foreach ($allInvoices as $invoice) {
            if ($invoice['uuid'] === $uuid) {
                return $invoice;
            }
        }
        return null;
    }

    /**
     * Dashboard özet istatistiklerini hesapla.
     * Gerçek uygulamada bu veriler veritabanı sorgularından gelir.
     */
    public function getSummary(string $vkn): array
    {
        // Bu ay kabul edilen satışları topla
        $monthlySales = array_filter(
            $this->demoSales,
            fn($i) => $i['status'] === 'ACCEPTED'
        );
        $salesTotal = array_sum(array_column($monthlySales, 'total'));

        // Bu ay kabul edilen alışları topla
        $monthlyPurchases = array_filter(
            $this->demoPurchases,
            fn($i) => $i['status'] === 'ACCEPTED'
        );
        $purchaseTotal = array_sum(array_column($monthlyPurchases, 'total'));

        // Bekleyen faturaları say
        $allInvoices = array_merge($this->demoSales, $this->demoPurchases);
        $pending = count(array_filter($allInvoices, fn($i) => $i['status'] === 'WAITING'));

        // KDV bakiyesi: Alış KDV - Satış KDV (devreden KDV hesabı)
        $salesVat    = array_sum(array_column($monthlySales, 'vatAmount'));
        $purchaseVat = array_sum(array_column($monthlyPurchases, 'vatAmount'));
        $vatBalance  = $purchaseVat - $salesVat;   // Pozitif: iade hakkı

        return [
            'monthlySales'         => $salesTotal,
            'monthlySalesCount'    => count($monthlySales),
            'monthlyPurchase'      => $purchaseTotal,
            'monthlyPurchaseCount' => count($monthlyPurchases),
            'pendingCount'         => $pending,
            'vatBalance'           => $vatBalance,
        ];
    }

    // ==================== ÖZEL YARDIMCI METODLAR ====================

    /**
     * Filtreleri uygula ve sayfalama yap.
     */
    private function applyFiltersAndPaginate(array $data, array $filters, int $limit, int $offset): array
    {
        $filtered = $this->applyFilters($data, $filters);
        return array_slice($filtered, $offset, $limit);
    }

    /**
     * Filtre parametrelerini fatura listesine uygula.
     * 
     * Desteklenen filtreler:
     *  - startDate  : YYYY-MM-DD formatında başlangıç tarihi
     *  - endDate    : YYYY-MM-DD formatında bitiş tarihi
     *  - status     : ACCEPTED | REJECTED | WAITING | DRAFT
     *  - docType    : EFATURA | EARSIV | EIRSALIYE
     *  - searchQuery: Fatura no veya firma adında metin arama
     */
    private function applyFilters(array $data, array $filters): array
    {
        return array_values(array_filter($data, function ($invoice) use ($filters) {

            // Başlangıç tarihi filtresi
            if (!empty($filters['startDate']) && $invoice['date'] < $filters['startDate']) {
                return false;
            }

            // Bitiş tarihi filtresi
            if (!empty($filters['endDate']) && $invoice['date'] > $filters['endDate']) {
                return false;
            }

            // Durum filtresi
            if (!empty($filters['status']) && $invoice['status'] !== $filters['status']) {
                return false;
            }

            // Belge tipi filtresi
            if (!empty($filters['docType']) && $invoice['docType'] !== $filters['docType']) {
                return false;
            }

            // Metin arama (fatura no veya firma adı)
            if (!empty($filters['searchQuery'])) {
                $query = strtolower($filters['searchQuery']);
                $inNumber = stripos($invoice['invoiceNumber'], $query) !== false;
                $inName   = stripos($invoice['partyName'],     $query) !== false;
                if (!$inNumber && !$inName) {
                    return false;
                }
            }

            return true; // Tüm filtrelerden geçti
        }));
    }
}
