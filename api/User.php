<?php
/**
 * User Sınıfı
 * Mükellef (kullanıcı) bilgilerini temsil eder.
 */
class User
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $vkn,         // Vergi Kimlik Numarası
        public readonly string $companyName,
        public readonly string $taxOffice    // Bağlı olduğu vergi dairesi
    ) {}
}
