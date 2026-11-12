<?php
declare(strict_types=1);

/**
 * Kullanıcıdan gelen sorgu parametrelerini doğrular ve güvenli değerlere çevirir.
 * Dizi enjeksiyonu (?status[]=x), geçersiz tarih ve bilinmeyen değerler sessizce elenir.
 */
final class Filters
{
    public const STATUSES  = ['ACCEPTED', 'REJECTED', 'WAITING', 'DRAFT'];
    public const DOC_TYPES = ['EFATURA', 'EARSIV', 'EIRSALIYE'];
    public const MAX_QUERY_LENGTH = 100;

    /** @return array{startDate:string,endDate:string,status:string,docType:string,searchQuery:string} */
    public static function fromQuery(array $query): array
    {
        return [
            'startDate'   => self::date($query['startDate'] ?? null),
            'endDate'     => self::date($query['endDate'] ?? null),
            'status'      => self::oneOf($query['status'] ?? null, self::STATUSES),
            'docType'     => self::oneOf($query['docType'] ?? null, self::DOC_TYPES),
            'searchQuery' => self::text($query['searchQuery'] ?? null),
        ];
    }

    public static function tab(array $query): string
    {
        $tab = $query['tab'] ?? null;
        return is_string($tab) && in_array($tab, ['sales', 'purchase'], true) ? $tab : 'sales';
    }

    public static function page(array $query): int
    {
        $page = $query['page'] ?? null;
        if (!is_string($page) || !ctype_digit($page)) {
            return 1;
        }
        return max(1, min((int) $page, 10000));
    }

    /** Demo UUID biçimi: INV-2024-S001 (S: satış, P: alış). */
    public static function uuid(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^INV-\d{4}-[SP]\d{3}$/', trim($value)) === 1
            ? trim($value)
            : null;
    }

    private static function date(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }
        $d = DateTime::createFromFormat('!Y-m-d', $value);
        return ($d !== false && $d->format('Y-m-d') === $value) ? $value : '';
    }

    /** @param string[] $allowed */
    private static function oneOf(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : '';
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        return mb_substr(trim($value), 0, self::MAX_QUERY_LENGTH);
    }
}