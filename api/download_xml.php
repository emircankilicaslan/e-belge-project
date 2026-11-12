<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
sendSecurityHeaders();

function fail(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$uuid = Filters::uuid($_GET['uuid'] ?? null);
if ($uuid === null) {
    fail(400, 'Geçerli bir uuid gereklidir.');
}
$invoice = (new InvoiceAPI())->getInvoiceByUUID($uuid);
if ($invoice === null) {
    fail(404, 'Fatura bulunamadı.');
}
if (!InvoiceXml::supports($invoice)) {
    fail(422, 'Bu belge tipi (e-İrsaliye) için UBL Invoice XML üretilemez.');
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $uuid . '.xml"');
echo InvoiceXml::build($invoice, currentUser());