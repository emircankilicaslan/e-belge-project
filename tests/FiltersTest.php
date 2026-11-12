<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FiltersTest extends TestCase
{
    public function testValidFiltersAreKept(): void
    {
        $f = Filters::fromQuery([
            'startDate' => '2024-11-01', 'endDate' => '2024-11-30',
            'status' => 'ACCEPTED', 'docType' => 'EFATURA', 'searchQuery' => '  tekno  ',
        ]);
        $this->assertSame('2024-11-01', $f['startDate']);
        $this->assertSame('2024-11-30', $f['endDate']);
        $this->assertSame('ACCEPTED', $f['status']);
        $this->assertSame('EFATURA', $f['docType']);
        $this->assertSame('tekno', $f['searchQuery']);
    }

    /** ?status[]=x gibi dizi girdileri eskiden TypeError ile sayfayı çökertiyordu. */
    public function testArrayInjectionIsIgnored(): void
    {
        $f = Filters::fromQuery([
            'startDate' => ['x'], 'status' => ['ACCEPTED'], 'docType' => ['EFATURA'], 'searchQuery' => ['a'],
        ]);
        $this->assertSame(['startDate' => '', 'endDate' => '', 'status' => '', 'docType' => '', 'searchQuery' => ''], $f);
    }

    #[DataProvider('invalidDates')]
    public function testInvalidDatesAreDropped(string $date): void
    {
        $this->assertSame('', Filters::fromQuery(['startDate' => $date])['startDate']);
    }

    public static function invalidDates(): array
    {
        return [['2024-02-30'], ['2024-13-01'], ['01.11.2024'], ['not-a-date'], ["2024-11-01'; DROP"]];
    }

    public function testUnknownStatusAndDocTypeAreDropped(): void
    {
        $f = Filters::fromQuery(['status' => 'HACKED', 'docType' => 'accepted']);
        $this->assertSame('', $f['status']);
        $this->assertSame('', $f['docType']);
    }

    public function testSearchQueryIsTruncated(): void
    {
        $f = Filters::fromQuery(['searchQuery' => str_repeat('ş', 500)]);
        $this->assertSame(Filters::MAX_QUERY_LENGTH, mb_strlen($f['searchQuery']));
    }

    public function testTabDefaultsToSales(): void
    {
        $this->assertSame('purchase', Filters::tab(['tab' => 'purchase']));
        $this->assertSame('sales', Filters::tab(['tab' => 'admin']));
        $this->assertSame('sales', Filters::tab(['tab' => ['purchase']]));
        $this->assertSame('sales', Filters::tab([]));
    }

    public function testPageParsing(): void
    {
        $this->assertSame(3, Filters::page(['page' => '3']));
        $this->assertSame(1, Filters::page(['page' => '-5']));
        $this->assertSame(1, Filters::page(['page' => '0']));
        $this->assertSame(1, Filters::page(['page' => 'abc']));
        $this->assertSame(1, Filters::page(['page' => ['2']]));
        $this->assertSame(10000, Filters::page(['page' => '99999999999']));
    }

    public function testUuidFormat(): void
    {
        $this->assertSame('INV-2024-S001', Filters::uuid(' INV-2024-S001 '));
        $this->assertSame('INV-2024-P004', Filters::uuid('INV-2024-P004'));
        foreach (['', 'INV-2024-X001', '../../etc/passwd', 'INV-2024-S001"><script>', null, ['INV-2024-S001']] as $bad) {
            $this->assertNull(Filters::uuid($bad), var_export($bad, true));
        }
    }
}