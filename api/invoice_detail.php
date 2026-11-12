<?php
declare(strict_types=1);
/**
 * Fatura Detay AJAX Endpoint
 * Kullanım: GET /api/invoice_detail.php?uuid=INV-2024-S001
 * Gerçek uygulamada oturum/token doğrulaması eklenmelidir.
 */
require_once __DIR__ . '/bootstrap.php';
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$uuid = Filters::uuid($_GET['uuid'] ?? null);
if ($uuid === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçerli bir uuid parametresi gereklidir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$invoice = (new InvoiceAPI())->getInvoiceByUUID($uuid);
if ($invoice === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Fatura bulunamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'invoice' => $invoice], JSON_UNESCAPED_UNICODE);