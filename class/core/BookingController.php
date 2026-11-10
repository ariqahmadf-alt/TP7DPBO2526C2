<?php
/**
 * BookingController
 *
 * Alur halaman Booking: daftar (pencarian + filter + pagination), form tambah,
 * form ubah, simpan, perbarui, dan hapus. Logika bisnis (bentrok, harga,
 * transaksi) ada di model Booking; controller hanya mengatur alur request.
 */
class BookingController
{
    private const PAGE = 'bookings';

    /** Field teks yang boleh diambil dari $_POST (frame_ids ditangani terpisah). */
    private const FIELDS = [
        'customer_id', 'package_id', 'booth_unit_id', 'event_name', 'venue', 'event_date',
        'start_time', 'end_time', 'overtime_hours', 'discount', 'status', 'notes',
        'dp_amount', 'dp_method',
    ];

    public function __construct(private Booking $booking)
    {
    }

    /** Memilih penanganan sesuai ?action=. */
    public function handle(string $action): void
    {
        switch ($action) {
            case 'create':
                $this->showForm(null);
                break;
            case 'edit':
                $this->showEdit();
                break;
            case 'store':
                $this->requirePost();
                $this->store();
                break;
            case 'update':
                $this->requirePost();
                $this->update();
                break;
            case 'delete':
                $this->requirePost();
                $this->destroy();
                break;
            default:
                $this->index();
        }
    }

    // ---------------- Tampilan ----------------

    /** Daftar booking dengan pencarian, filter status/tanggal, dan pagination. */
    private function index(): void
    {
        // Filter dibaca dari query string; hanya nilai string yang diterima.
        $filters = [];
        foreach (['q', 'status', 'date_from', 'date_to'] as $key) {
            $filters[$key] = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        }

        $current   = max(1, (int) ($_GET['p'] ?? 1));
        $paginator = new Paginator($this->booking->countSearch($filters), PER_PAGE, $current);
        $rows      = $this->booking->search($filters, $paginator->perPage(), $paginator->offset());

        $this->render([
            'mode' => 'list', 'rows' => $rows, 'paginator' => $paginator, 'filters' => $filters,
            // Filter ikut dibawa di link pagination.
            'paginationParams' => array_filter($filters, fn($v) => $v !== ''),
            'item' => null,
        ]);
    }

    /** Form tambah (item null) atau ubah (item berisi data booking). */
    private function showForm(?array $item): void
    {
        $customers = [];
        foreach ((new Customer())->all() as $c) {
            $customers[$c['id']] = $c['name'] . ($c['organization'] ? ' (' . $c['organization'] . ')' : '');
        }

        $packages = [];
        foreach ((new Package())->all() as $p) {
            $packages[$p['id']] = $p['name'] . ' - ' . Html::rupiah($p['price']) . ' / ' . $p['duration_hours'] . ' jam';
        }

        // Hanya unit tersedia yang bisa dipilih; unit lama booking (bila sedang
        // perawatan) tetap ditampilkan agar form ubah tidak kehilangan nilainya.
        $booths = [];
        $boothModel = new BoothUnit();
        foreach ($boothModel->availableUnits() as $u) {
            $booths[$u['id']] = $u['code'] . ' - ' . $u['type'];
        }
        if ($item !== null && !isset($booths[$item['booth_unit_id']])) {
            $current = $boothModel->find((int) $item['booth_unit_id']);
            if ($current !== null) {
                $booths[$current['id']] = $current['code'] . ' - ' . $current['type'] . ' (perawatan)';
            }
        }

        $selectedFrames = [];
        if ($item !== null) {
            $selectedFrames = $this->booking->frameIds((int) $item['id']);
            // Jam dari database (H:i:s) dipotong menjadi H:i untuk input type="time".
            $item['start_time'] = substr($item['start_time'], 0, 5);
            $item['end_time']   = substr($item['end_time'], 0, 5);
        }

        $this->render([
            'mode' => 'form', 'item' => $item, 'rows' => [], 'paginator' => null,
            'customerOptions' => $customers, 'packageOptions' => $packages, 'boothOptions' => $booths,
            'frames' => (new Frame())->all(), 'selectedFrames' => $selectedFrames,
            'overtimeRate' => OVERTIME_RATE,
        ]);
    }

    /** Memuat booking yang akan diubah. */
    private function showEdit(): void
    {
        $item = $this->booking->findDetail((int) ($_GET['id'] ?? 0));
        if ($item === null) {
            $this->notFound();
        }
        $this->showForm($item);
    }

    // ---------------- Aksi POST ----------------

    private function store(): void
    {
        Csrf::validateRequest();
        $input = $this->collectInput();

        try {
            $id = $this->booking->create($input);
            Flash::success('Booking #' . $id . ' berhasil dibuat.');
            Http::redirect(Http::url(['page' => self::PAGE]));
        } catch (ValidationException $e) {
            Flash::withValidation($input, $e->getErrors());
            Flash::error($e->getMessage());
        } catch (BookingConflictException $e) {
            Flash::withValidation($input, []);
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            // Transaksi sudah di-rollback oleh model; tidak ada data setengah tersimpan.
            error_log('Gagal membuat booking: ' . $e->getMessage());
            Flash::withValidation($input, []);
            Flash::error('Booking gagal disimpan karena kesalahan database. Tidak ada data yang tersimpan.');
        }

        Http::redirect(Http::url(['page' => self::PAGE, 'action' => 'create']));
    }

    private function update(): void
    {
        Csrf::validateRequest();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->booking->exists($id)) {
            $this->notFound();
        }
        $input = $this->collectInput();

        try {
            $this->booking->update($id, $input);
            Flash::success('Booking #' . $id . ' berhasil diperbarui.');
            Http::redirect(Http::url(['page' => self::PAGE]));
        } catch (ValidationException $e) {
            Flash::withValidation($input, $e->getErrors());
            Flash::error($e->getMessage());
        } catch (BookingConflictException $e) {
            Flash::withValidation($input, []);
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            error_log('Gagal memperbarui booking: ' . $e->getMessage());
            Flash::withValidation($input, []);
            Flash::error('Booking gagal diperbarui karena kesalahan database. Perubahan dibatalkan.');
        }

        Http::redirect(Http::url(['page' => self::PAGE, 'action' => 'edit', 'id' => $id]));
    }

    private function destroy(): void
    {
        Csrf::validateRequest();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->booking->exists($id)) {
            $this->notFound();
        }

        try {
            $this->booking->delete($id);
            Flash::success('Booking berhasil dihapus.');
        } catch (DeleteRestrictedException $e) {
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            error_log('Gagal menghapus booking: ' . $e->getMessage());
            Flash::error('Booking tidak dapat dihapus karena masih terhubung dengan data lain.');
        }

        Http::redirect(Http::url(['page' => self::PAGE]));
    }

    // ---------------- Pembantu ----------------

    /** Mengambil input form: field teks + daftar frame yang dicentang. */
    private function collectInput(): array
    {
        $input = [];
        foreach (self::FIELDS as $field) {
            $value = $_POST[$field] ?? '';
            $input[$field] = is_string($value) ? trim($value) : '';
        }

        $input['frame_ids'] = [];
        if (is_array($_POST['frame_ids'] ?? null)) {
            foreach ($_POST['frame_ids'] as $fid) {
                if (is_string($fid)) {
                    $input['frame_ids'][] = $fid;
                }
            }
        }
        return $input;
    }

    private function requirePost(): void
    {
        if (!Http::isPost()) {
            Http::redirect(Http::url(['page' => self::PAGE]));
        }
    }

    private function notFound(): void
    {
        Flash::error('Booking tidak ditemukan.');
        Http::redirect(Http::url(['page' => self::PAGE]));
    }

    private function render(array $data): void
    {
        $data['currentPage'] = self::PAGE;
        $data['label']       = 'Booking';
        extract($data, EXTR_SKIP);

        $viewDir = dirname(__DIR__, 2) . '/view/';
        require $viewDir . 'header.php';
        require $viewDir . 'bookings.php';
        require $viewDir . 'footer.php';
    }
}
