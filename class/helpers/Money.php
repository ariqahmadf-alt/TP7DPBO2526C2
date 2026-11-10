<?php
/**
 * Money
 *
 * Perhitungan uang memakai bilangan bulat dalam satuan sen agar bebas dari
 * kesalahan pembulatan floating point (mis. 0.1 + 0.2). Nilai dari/ke database
 * berupa string desimal "1500000.00".
 */
class Money
{
    /** String/angka rupiah -> sen (integer). */
    public static function toCents(string|int|float|null $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /** Sen (integer) -> string desimal 2 digit untuk disimpan ke DECIMAL(12,2). */
    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
