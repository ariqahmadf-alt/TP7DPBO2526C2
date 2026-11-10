<?php
/**
 * BookingConflictException
 *
 * Dilempar ketika jadwal booking bentrok dengan booking lain pada unit booth
 * yang sama. Menyimpan data booking yang bentrok agar pesan bisa spesifik.
 */
class BookingConflictException extends Exception
{
    /** @var array<string,mixed> Data booking yang bentrok (id, event_name, jam, dll). */
    private array $conflict;

    /**
     * @param string $message  Pesan yang menyebut booking mana yang bentrok.
     * @param array  $conflict Baris data booking yang bentrok.
     */
    public function __construct(string $message, array $conflict = [])
    {
        parent::__construct($message);
        $this->conflict = $conflict;
    }

    /** Mengambil data booking yang bentrok. */
    public function getConflict(): array
    {
        return $this->conflict;
    }
}
