<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InvoiceXmlTest extends TestCase
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private User $me;

    protected function setUp(): void
    {
        $this->me = new User(1, 'Ahmet', '1234567890', 'Yılmaz Ticaret A.Ş.', 'Kadıköy VD');
    }

    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'XML iyi biçimli olmalı');
        $x = new DOMXPath($doc);
        $x->registerNamespace('cbc', self::CBC);
        $x->registerNamespace('cac', self::CAC);
        $x->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        return $x;
    }

    private function invoice(string $uuid): array
    {
        return (new InvoiceAPI())->getInvoiceByUUID($uuid);
    }

    public function testSalesInvoiceHasUserAsSupplier(): void
    {
        $x = $this->xpath(InvoiceXml::build($this->invoice('INV-2024-S001'), $this->me));

        $this->assertSame('YLM2024000001', $x->evaluate('string(/inv:Invoice/cbc:ID)'));
        $this->assertSame('INV-2024-S001', $x->evaluate('string(/inv:Invoice/cbc:UUID)'));
        $this->assertSame('TICARIFATURA', $x->evaluate('string(/inv:Invoice/cbc:ProfileID)'));
        $this->assertSame('Yılmaz Ticaret A.Ş.', $x->evaluate('string(//cac:AccountingSupplierParty//cac:PartyName/cbc:Name)'));
        $this->assertSame('Teknoloji A.Ş.', $x->evaluate('string(//cac:AccountingCustomerParty//cac:PartyName/cbc:Name)'));
        $this->assertSame('VKN', $x->evaluate('string(//cac:AccountingSupplierParty//cac:PartyIdentification/cbc:ID/@schemeID)'));
    }

    public function testPurchaseInvoiceSwapsParties(): void
    {
        $x = $this->xpath(InvoiceXml::build($this->invoice('INV-2024-P001'), $this->me));
        $this->assertSame('Tedarikçi A.Ş.', $x->evaluate('string(//cac:AccountingSupplierParty//cac:PartyName/cbc:Name)'));
        $this->assertSame('Yılmaz Ticaret A.Ş.', $x->evaluate('string(//cac:AccountingCustomerParty//cac:PartyName/cbc:Name)'));
    }

    public function testAmountsAndCurrency(): void
    {
        $x = $this->xpath(InvoiceXml::build($this->invoice('INV-2024-S003'), $this->me)); // EUR ihracat
        $this->assertSame('120000.00', $x->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount)'));
        $this->assertSame('EUR', $x->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount/@currencyID)'));
        $this->assertSame('EUR', $x->evaluate('string(/inv:Invoice/cbc:DocumentCurrencyCode)'));
        $this->assertSame('0.00', $x->evaluate('string(//cac:TaxTotal/cbc:TaxAmount)'));
    }

    public function testEArsivUsesEArsivProfile(): void
    {
        $x = $this->xpath(InvoiceXml::build($this->invoice('INV-2024-S002'), $this->me));
        $this->assertSame('EARSIVFATURA', $x->evaluate('string(/inv:Invoice/cbc:ProfileID)'));
        $this->assertSame('TCKN', $x->evaluate('string(//cac:AccountingCustomerParty//cac:PartyIdentification/cbc:ID/@schemeID)'));
    }

    /** Özel karakterler kaçışlanmalı; XML bozulmamalı ve enjeksiyon yapılamamalı. */
    public function testSpecialCharactersAreEscaped(): void
    {
        $inv = $this->invoice('INV-2024-S001');
        $inv['partyName'] = 'A & B <Ltd> "x" </cbc:Name><evil/>';
        $x = $this->xpath(InvoiceXml::build($inv, $this->me));

        $this->assertSame($inv['partyName'], $x->evaluate('string(//cac:AccountingCustomerParty//cac:PartyName/cbc:Name)'));
        $this->assertSame(0, $x->query('//evil')->length);
    }

    public function testEIrsaliyeIsNotSupported(): void
    {
        $inv = $this->invoice('INV-2024-P003');
        $this->assertFalse(InvoiceXml::supports($inv));
        $this->expectException(InvalidArgumentException::class);
        InvoiceXml::build($inv, $this->me);
    }
}