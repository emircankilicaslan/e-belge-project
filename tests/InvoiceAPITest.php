<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InvoiceAPITest extends TestCase
{
    private InvoiceAPI $api;

    protected function setUp(): void
    {
        $this->api = new InvoiceAPI();
    }

    public function testCountsWithoutFilters(): void
    {
        $this->assertSame(5, $this->api->getSalesCount('1', []));
        $this->assertSame(4, $this->api->getPurchaseCount('1', []));
    }

    public function testFilterByStatusAndDocType(): void
    {
        $waiting = $this->api->getSalesInvoices('1', ['status' => 'WAITING']);
        $this->assertCount(1, $waiting);
        $this->assertSame('INV-2024-S004', $waiting[0]['uuid']);

        $this->assertSame(3, $this->api->getSalesCount('1', ['docType' => 'EFATURA']));
    }

    public function testFilterByDateRangeIsInclusive(): void
    {
        $r = $this->api->getSalesInvoices('1', ['startDate' => '2024-11-05', 'endDate' => '2024-11-15']);
        $this->assertSame(['INV-2024-S002', 'INV-2024-S003', 'INV-2024-S004'], array_column($r, 'uuid'));
    }

    public function testSearchIsCaseInsensitiveOnNameAndNumber(): void
    {
        $this->assertCount(1, $this->api->getSalesInvoices('1', ['searchQuery' => 'teknoloji']));
        $this->assertCount(2, $this->api->getSalesInvoices('1', ['searchQuery' => 'MÜŞTERI']));  // Türkçe karakter, büyük harf (2 firma)
        $this->assertCount(1, $this->api->getSalesInvoices('1', ['searchQuery' => 'ylm2024000003']));
        $this->assertCount(0, $this->api->getSalesInvoices('1', ['searchQuery' => 'yok-boyle-bir-sey']));
    }

    public function testPagination(): void
    {
        $page1 = $this->api->getSalesInvoices('1', [], 2, 0);
        $page3 = $this->api->getSalesInvoices('1', [], 2, 4);
        $this->assertCount(2, $page1);
        $this->assertCount(1, $page3);
        $this->assertSame('INV-2024-S005', $page3[0]['uuid']);
        $this->assertSame([], $this->api->getSalesInvoices('1', [], 2, 10));
    }

    public function testLookupByUuidReturnsDirection(): void
    {
        $this->assertSame('SALES', $this->api->getInvoiceByUUID('INV-2024-S001')['direction']);
        $this->assertSame('PURCHASE', $this->api->getInvoiceByUUID('INV-2024-P001')['direction']);
        $this->assertNull($this->api->getInvoiceByUUID('INV-2024-S999'));
    }

    /** Farklı para birimleri (EUR ihracat faturası) TL toplamına karıştırılmamalı. */
    public function testSummaryOnlySumsTryInvoices(): void
    {
        $s = $this->api->getSummary('1');
        $this->assertEqualsWithDelta(70200.0, $s['monthlySales'], 0.001);   // 60.000 + 10.200 (EUR hariç)
        $this->assertSame(2, $s['monthlySalesCount']);
        $this->assertEqualsWithDelta(49200.0, $s['monthlyPurchase'], 0.001);
        $this->assertSame(2, $s['pendingCount']);
        $this->assertEqualsWithDelta(8200.0 - 11700.0, $s['vatBalance'], 0.001);
    }
}