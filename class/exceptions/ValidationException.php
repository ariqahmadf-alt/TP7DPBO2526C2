<?php
/**
 * ValidationException
 *
 * Dilempar ketika input form tidak lolos validasi. Membawa array pesan error
 * per field agar form dapat menampilkan pesan tepat di bawah input yang salah.
 */
class ValidationException extends Exception
{
    /** @var array<string,string> Pesan error, kuncinya nama field. */
    private array $errors;

    /**
     * @param array<string,string> $errors Pesan error per field.
     */
    public function __construct(array $errors, string $message = 'Data yang diisi belum valid.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /** Mengambil seluruh pesan error per field. */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
