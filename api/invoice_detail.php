<?php
/**
 * Fatura Detay AJAX Endpoint
 * 
 * JavaScript tarafından çağrılır.
 * UUID alır, fatura detaylarını JSON formatında döndürür.
 * 
 * Kullanım: GET /api/invoice_detail.php?uuid=INV-2024-S001
 */

// Sadece AJAX isteklerini kabul et
// Gerçek uygulamada token doğrulaması da eklenmeli
header('Content-Type: application/json; charset=utf-8');

require_once 'InvoiceAPI.php';

// UUID parametresini al ve temizle
$uuid = trim($_GET['uuid'] ?? '');

// UUID boşsa hata döndür
if (empty($uuid)) {
    http_response_code(400);
    echo json_encode(['error' => 'UUID parametresi gereklidir.']);
    exit;
}

$api     = new InvoiceAPI();
$invoice = $api->getInvoiceByUUID($uuid);

// Fatura bulunamadıysa 404 döndür
if (!$invoice) {
    http_response_code(404);
    echo json_encode(['error' => 'Fatura bulunamadı.']);
    exit;
}

// Başarılı yanıt - fatura verilerini JSON olarak döndür
echo json_encode([
    'success' => true,
    'invoice' => $invoice,
]);
