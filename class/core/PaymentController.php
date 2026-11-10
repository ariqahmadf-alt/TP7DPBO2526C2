<?php
/**
 * PaymentController
 *
 * Alur halaman Pembayaran: daftar (opsional per booking), form catat
 * pembayaran, ubah, dan hapus. Aturan nominal/tagihan ada di model Payment.
 */
class PaymentController
{
    private const PAGE = 'payments';
    private const FIELDS = ['booking_id', 'amount', 'type', 'method', 'paid_at', 'note'];

    public function __construct(private Payment $payment, private Booking $booking)
    {
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'create':
                $this->showCreate();
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

    /** Daftar pembayaran; ?booking_id= menyaring pembayaran satu booking. */
    private function index(): void
    {
        $bookingId = (int) ($_GET['booking_id'] ?? 0);
        $filterId  = $bookingId > 0 ? $bookingId : null;

        $paginator = new Paginator(
            $this->payment->countFor($filterId), PER_PAGE, max(1, (int) ($_GET['p'] ?? 1))
        );
        $rows = $this->payment->search($filterId, $paginator->perPage(), $paginator->offset());

        $this->render([
            'mode' => 'list', 'rows' => $rows, 'paginator' => $paginator, 'item' => null,
            'filterBooking' => $filterId !== null ? $this->booking->findDetail($filterId) : null,
            'paginationParams' => $filterId !== null ? ['booking_id' => $filterId] : [],
        ]);
    }

    /** Form catat pembayaran baru; ?booking_id= memilih booking awal. */
    private function showCreate(): void
    {
        $options = [];
        foreach ($this->booking->payableOptions() as $b) {
            $options[$b['id']] = '#' . $b['id'] . ' ' . $b['event_name'] . ' - ' . $b['customer_name']
                . ' (sisa ' . Html::rupiah($b['summary']['remaining']) . ')';
        }

        $this->render([
            'mode' => 'form', 'item' => null, 'rows' => [], 'paginator' => null,
            'bookingOptions' => $options, 'defaultBookingId' => (int) ($_GET['booking_id'] ?? 0),
            'bookingInfo' => null,
        ]);
    }

    /** Form ubah pembayaran (booking tidak dapat dipindah). */
    private function showEdit(): void
    {
        $item = $this->payment->find((int) ($_GET['id'] ?? 0));
        if ($item === null) {
            $this->notFound();
        }

        // Format MySQL -> format input datetime-local.
        $item['paid_at'] = date('Y-m-d\TH:i', strtotime($item['paid_at']));

        $this->render([
            'mode' => 'form', 'item' => $item, 'rows' => [], 'paginator' => null,
            'bookingOptions' => [], 'defaultBookingId' => 0,
            'bookingInfo' => $this->booking->findDetail((int) $item['booking_id']),
        ]);
    }

    // ---------------- Aksi POST ----------------

    private function store(): void
    {
        Csrf::validateRequest();
        $input = $this->collectInput();

        try {
            $this->payment->create($input);
            Flash::success('Pembayaran berhasil dicatat.');
            Http::redirect(Http::url(['page' => self::PAGE]));
        } catch (ValidationException $e) {
            Flash::withValidation($input, $e->getErrors());
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            error_log('Gagal mencatat pembayaran: ' . $e->getMessage());
            Flash::withValidation($input, []);
            Flash::error('Pembayaran gagal disimpan karena kesalahan database.');
        }

        Http::redirect(Http::url(['page' => self::PAGE, 'action' => 'create', 'booking_id' => $input['booking_id']]));
    }

    private function update(): void
    {
        Csrf::validateRequest();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->payment->exists($id)) {
            $this->notFound();
        }
        $input = $this->collectInput();

        try {
            $this->payment->update($id, $input);
            Flash::success('Pembayaran berhasil diperbarui.');
            Http::redirect(Http::url(['page' => self::PAGE]));
        } catch (ValidationException $e) {
            Flash::withValidation($input, $e->getErrors());
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            error_log('Gagal memperbarui pembayaran: ' . $e->getMessage());
            Flash::withValidation($input, []);
            Flash::error('Pembayaran gagal diperbarui karena kesalahan database.');
        }

        Http::redirect(Http::url(['page' => self::PAGE, 'action' => 'edit', 'id' => $id]));
    }

    private function destroy(): void
    {
        Csrf::validateRequest();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->payment->exists($id)) {
            $this->notFound();
        }

        try {
            $this->payment->delete($id);
            Flash::success('Pembayaran berhasil dihapus.');
        } catch (DeleteRestrictedException $e) {
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            error_log('Gagal menghapus pembayaran: ' . $e->getMessage());
            Flash::error('Pembayaran tidak dapat dihapus karena kesalahan database.');
        }

        Http::redirect(Http::url(['page' => self::PAGE]));
    }

    // ---------------- Pembantu ----------------

    private function collectInput(): array
    {
        $input = [];
        foreach (self::FIELDS as $field) {
            $value = $_POST[$field] ?? '';
            $input[$field] = is_string($value) ? trim($value) : '';
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
        Flash::error('Pembayaran tidak ditemukan.');
        Http::redirect(Http::url(['page' => self::PAGE]));
    }

    private function render(array $data): void
    {
        $data['currentPage'] = self::PAGE;
        $data['label']       = 'Pembayaran';
        extract($data, EXTR_SKIP);

        $viewDir = dirname(__DIR__, 2) . '/view/';
        require $viewDir . 'header.php';
        require $viewDir . 'payments.php';
        require $viewDir . 'footer.php';
    }
}
