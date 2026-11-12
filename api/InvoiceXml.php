<?php
declare(strict_types=1);

/**
 * Bir faturadan sadeleştirilmiş UBL-TR (UBL 2.1) XML üretir.
 *
 * Not: Demo amaçlıdır. Fatura satırları, dijital imza (XAdES) ve GİB şema doğrulaması
 * yoktur; üretimde GİB onaylı entegratör bileşenleri kullanılmalıdır.
 */
final class InvoiceXml
{
    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private DOMDocument $doc;

    /** e-İrsaliye bir "Invoice" belgesi değildir, bu yüzden XML üretilmez. */
    public static function supports(array $invoice): bool
    {
        return in_array($invoice['docType'] ?? '', ['EFATURA', 'EARSIV'], true);
    }

    public static function build(array $invoice, User $self): string
    {
        if (!self::supports($invoice)) {
            throw new InvalidArgumentException('Bu belge tipi için UBL Invoice üretilemez.');
        }
        return (new self())->render($invoice, $self);
    }

    private function render(array $inv, User $self): string
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;

        $root = $this->doc->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $this->doc->appendChild($root);

        $currency = (string) $inv['currency'];
        $isSale   = ($inv['direction'] ?? 'SALES') === 'SALES';

        $this->cbc($root, 'UBLVersionID', '2.1');
        $this->cbc($root, 'CustomizationID', 'TR1.2');
        $this->cbc($root, 'ProfileID', $inv['docType'] === 'EARSIV' ? 'EARSIVFATURA' : 'TICARIFATURA');
        $this->cbc($root, 'ID', (string) $inv['invoiceNumber']);
        $this->cbc($root, 'UUID', (string) $inv['uuid']);
        $this->cbc($root, 'IssueDate', (string) $inv['date']);
        $this->cbc($root, 'InvoiceTypeCode', 'SATIS');
        $this->cbc($root, 'DocumentCurrencyCode', $currency);

        $mine   = ['name' => $self->companyName, 'vkn' => $self->vkn];
        $theirs = ['name' => (string) $inv['partyName'], 'vkn' => (string) $inv['partyVkn']];
        $this->party($root, 'AccountingSupplierParty', $isSale ? $mine : $theirs);
        $this->party($root, 'AccountingCustomerParty', $isSale ? $theirs : $mine);

        $tax = $this->cac($root, 'TaxTotal');
        $this->amount($tax, 'TaxAmount', (float) $inv['vatAmount'], $currency);
        $sub = $this->cac($tax, 'TaxSubtotal');
        $this->amount($sub, 'TaxableAmount', (float) $inv['subtotal'], $currency);
        $this->amount($sub, 'TaxAmount', (float) $inv['vatAmount'], $currency);
        $this->cbc($sub, 'Percent', number_format((float) $inv['vatRate'], 2, '.', ''));
        $cat = $this->cac($sub, 'TaxCategory');
        $scheme = $this->cac($cat, 'TaxScheme');
        $this->cbc($scheme, 'Name', 'KDV');
        $this->cbc($scheme, 'TaxTypeCode', '0015');

        $total = $this->cac($root, 'LegalMonetaryTotal');
        $this->amount($total, 'LineExtensionAmount', (float) $inv['subtotal'], $currency);
        $this->amount($total, 'TaxExclusiveAmount', (float) $inv['subtotal'], $currency);
        $this->amount($total, 'TaxInclusiveAmount', (float) $inv['total'], $currency);
        $this->amount($total, 'PayableAmount', (float) $inv['total'], $currency);

        return $this->doc->saveXML() ?: '';
    }

    private function cbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        // createElementNS değeri ayrıca kaçışlamaz; textContent ataması özel karakterleri güvenle kodlar.
        $el = $this->doc->createElementNS(self::NS_CBC, 'cbc:' . $name);
        $el->appendChild($this->doc->createTextNode($value));
        $parent->appendChild($el);
        return $el;
    }

    private function amount(DOMElement $parent, string $name, float $value, string $currency): void
    {
        $el = $this->cbc($parent, $name, number_format($value, 2, '.', ''));
        $el->setAttribute('currencyID', $currency);
    }

    private function cac(DOMElement $parent, string $name): DOMElement
    {
        $el = $this->doc->createElementNS(self::NS_CAC, 'cac:' . $name);
        $parent->appendChild($el);
        return $el;
    }

    /** @param array{name:string,vkn:string} $p */
    private function party(DOMElement $root, string $role, array $p): void
    {
        $wrap  = $this->cac($root, $role);
        $party = $this->cac($wrap, 'Party');
        $id = $this->cbc($this->cac($party, 'PartyIdentification'), 'ID', $p['vkn']);
        $id->setAttribute('schemeID', strlen($p['vkn']) === 11 ? 'TCKN' : 'VKN');
        $this->cbc($this->cac($party, 'PartyName'), 'Name', $p['name']);
    }
}