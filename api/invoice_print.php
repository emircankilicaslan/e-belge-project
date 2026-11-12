<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
sendSecurityHeaders();

$uuid = Filters::uuid($_GET['uuid'] ?? null);
$inv  = $uuid === null ? null : (new InvoiceAPI())->getInvoiceByUUID($uuid);
if ($inv === null) {
    http_response_code($uuid === null ? 400 : 404);
    echo 'Fatura bulunamadı.';
    exit;
}

$me   = currentUser();
$isSale = ($inv['direction'] ?? 'SALES') === 'SALES';
$e    = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$money = static fn(float $v): string => number_format($v, 2, ',', '.') . ' ' . $inv['currency'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= $e($inv['invoiceNumber']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #111; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 24px; }
        .parties { display: flex; gap: 48px; margin-bottom: 24px; }
        table { border-collapse: collapse; width: 360px; }
        td { padding: 6px 0; border-bottom: 1px solid #ddd; }
        td:last-child { text-align: right; font-family: monospace; }
        .total td { font-weight: bold; border-bottom: 2px solid #111; }
        .note { margin-top: 32px; font-size: 12px; color: #777; }
        @media print { .note { display: none; } }
    </style>
</head>
<body>
    <h1><?= $e($inv['docType']) ?> — <?= $e($inv['invoiceNumber']) ?></h1>
    <div class="meta">ETTN: <?= $e($inv['uuid']) ?> · Tarih: <?= $e(date('d.m.Y', strtotime($inv['date']))) ?></div>
    <div class="parties">
        <div><strong>Satıcı</strong><br>
            <?= $e($isSale ? $me->companyName : $inv['partyName']) ?><br>
            VKN/TCKN: <?= $e($isSale ? $me->vkn : $inv['partyVkn']) ?></div>
        <div><strong>Alıcı</strong><br>
            <?= $e($isSale ? $inv['partyName'] : $me->companyName) ?><br>
            VKN/TCKN: <?= $e($isSale ? $inv['partyVkn'] : $me->vkn) ?></div>
    </div>
    <table>
        <tr><td>Mal/Hizmet Toplamı</td><td><?= $e($money((float) $inv['subtotal'])) ?></td></tr>
        <tr><td>KDV (%<?= $e($inv['vatRate']) ?>)</td><td><?= $e($money((float) $inv['vatAmount'])) ?></td></tr>
        <tr class="total"><td>Ödenecek Tutar</td><td><?= $e($money((float) $inv['total'])) ?></td></tr>
    </table>
    <p class="note">Demo çıktısıdır; yasal e-Fatura görseli değildir. PDF olarak kaydetmek için tarayıcının yazdır diyaloğunu kullanın.</p>
    <?php if (($_GET['print'] ?? '') === '1'): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
</body>
</html>