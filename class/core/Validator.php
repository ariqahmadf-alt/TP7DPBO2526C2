<?php
/**
 * Trait Validator
 *
 * Kumpulan aturan validasi yang dapat dipakai ulang oleh semua class model.
 * Dipilih trait (bukan inheritance) karena validasi bersifat "kemampuan
 * tambahan" yang bisa dipasang ke class mana pun tanpa mengubah hierarki.
 *
 * Contoh pemakaian di model:
 *   $this->validate($data, [
 *       'name'  => ['required', 'max:100'],
 *       'email' => ['email', 'max:100'],
 *   ], ['name' => 'Nama', 'email' => 'Email']);
 *
 * Aturan yang tersedia:
 *   required, numeric, integer, positive (> 0), nonnegative (>= 0),
 *   decimal (uang), phone, between:a,b, email, max:N, date (Y-m-d),
 *   time (H:i / H:i:s), in:a,b,c
 * Field yang kosong dan tidak 'required' dianggap valid (opsional).
 */
trait Validator
{
    /**
     * Menjalankan validasi. Bila ada yang gagal, dilempar ValidationException
     * berisi satu pesan error pertama untuk setiap field.
     *
     * @param array $data   Data input (mis. dari $_POST).
     * @param array $rules  Peta field => daftar aturan.
     * @param array $labels Peta field => nama tampilan untuk pesan error.
     * @throws ValidationException
     */
    protected function validate(array $data, array $rules, array $labels = []): void
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

            // Nilai dianggap kosong bila null atau hanya berisi spasi.
            $isEmpty = ($value === null || (is_string($value) && trim($value) === ''));

            foreach ($fieldRules as $rule) {
                // Pisahkan nama aturan dan parameternya, mis. "max:100".
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                // Aturan selain 'required' dilewati bila field opsional dan kosong.
                if ($isEmpty && $name !== 'required') {
                    continue;
                }

                $message = $this->checkRule($name, $param, $value, $label);
                if ($message !== null) {
                    $errors[$field] = $message; // hanya pesan pertama per field
                    break;
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Memeriksa satu aturan. Mengembalikan pesan error, atau null bila lolos.
     */
    private function checkRule(string $name, ?string $param, mixed $value, string $label): ?string
    {
        switch ($name) {
            case 'required':
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    return "$label wajib diisi.";
                }
                return null;

            case 'numeric':
                return is_numeric($value) ? null : "$label harus berupa angka.";

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false
                    ? null : "$label harus berupa bilangan bulat.";

            case 'positive':
                return (is_numeric($value) && $value > 0)
                    ? null : "$label harus lebih besar dari 0.";

            case 'nonnegative':
                return (is_numeric($value) && $value >= 0)
                    ? null : "$label tidak boleh negatif.";

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                    ? null : "Format $label tidak valid.";

            case 'max':
                return mb_strlen((string) $value) <= (int) $param
                    ? null : "$label maksimal $param karakter.";

            case 'date':
                return $this->isValidDate((string) $value)
                    ? null : "$label bukan tanggal yang valid (format YYYY-MM-DD).";

            case 'time':
                return $this->isValidTime((string) $value)
                    ? null : "$label bukan jam yang valid (format HH:MM).";

            case 'phone':
                // Boleh diawali +, berisi angka, spasi, atau tanda hubung (8-20 karakter).
                return preg_match('/^\+?[0-9][0-9\s\-]{6,19}$/', trim((string) $value))
                    ? null : "$label harus berupa nomor telepon yang valid.";

            case 'decimal':
                // Uang: hanya angka, maksimal 10 digit bulat dan 2 digit desimal (sesuai DECIMAL(12,2)).
                return preg_match('/^\d{1,10}(\.\d{1,2})?$/', trim((string) $value))
                    ? null : "$label harus berupa angka (maksimal 2 desimal, tanpa titik/koma ribuan).";

            case 'between':
                [$min, $max] = array_map('intval', explode(',', (string) $param));
                return (is_numeric($value) && $value >= $min && $value <= $max)
                    ? null : "$label harus antara $min dan $max.";

            case 'in':
                $allowed = explode(',', (string) $param);
                return in_array((string) $value, $allowed, true)
                    ? null : "$label berisi pilihan yang tidak dikenal.";
        }

        // Aturan tidak dikenal dianggap kesalahan programmer, bukan kesalahan input.
        throw new InvalidArgumentException("Aturan validasi '$name' tidak dikenal.");
    }

    /** Memastikan string adalah tanggal Y-m-d yang benar-benar ada (mis. 31 Februari ditolak). */
    protected function isValidDate(string $value): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    /** Memastikan string adalah jam H:i atau H:i:s yang valid. */
    protected function isValidTime(string $value): bool
    {
        foreach (['H:i', 'H:i:s'] as $format) {
            $t = DateTime::createFromFormat($format, $value);
            if ($t !== false && $t->format($format) === $value) {
                return true;
            }
        }
        return false;
    }
}
