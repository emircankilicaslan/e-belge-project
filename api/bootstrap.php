<?php
declare(strict_types=1);

/**
 * Ortak başlangıç: hata gösterimi, sınıf yükleme ve demo kullanıcı.
 * Hatalar yalnızca APP_DEBUG=1 ortam değişkeniyle ekrana yazdırılır (üretimde kapalı).
 */
$debug = getenv('APP_DEBUG') === '1';
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Filters.php';
require_once __DIR__ . '/InvoiceAPI.php';
require_once __DIR__ . '/InvoiceXml.php';

/** Oturum açmış mükellef (demo). Gerçek uygulamada kimlik doğrulamadan gelir. */
function currentUser(): User
{
    return new User(
        id: 1,
        name: 'Ahmet Yılmaz',
        vkn: '1234567890',
        companyName: 'Yılmaz Ticaret A.Ş.',
        taxOffice: 'Kadıköy VD'
    );
}

/** Güvenlik başlıkları (tüm endpoint'lerde ortak). */
function sendSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
}