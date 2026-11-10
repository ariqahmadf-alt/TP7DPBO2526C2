<?php
/**
 * CrudController
 *
 * Penangan generik untuk entitas sederhana (customer, paket, frame, unit booth).
 * Satu class ini mengurus alur lengkap: daftar, form tambah, form ubah, simpan,
 * perbarui, dan hapus. Logika data tetap ada di model; view hanya menampilkan.
 *
 * Alur tiap aksi:
 *   GET  index   -> ambil data dari model -> render view (mode "list")
 *   GET  create  -> render view (mode "form", tanpa data)
 *   GET  edit    -> ambil satu data -> render view (mode "form", dengan data)
 *   POST store / update / delete -> verifikasi CSRF -> panggil model
 *        -> sukses: Flash + redirect ke daftar (Post-Redirect-Get)
 *        -> gagal : Flash + old input + redirect kembali ke form
 */
class CrudController
{
    /**
     * @param BaseModel $model    Model entitas yang dikelola.
     * @param string    $page     Nama halaman pada URL (mis. "customers").
     * @param string    $label    Nama entitas untuk pesan (mis. "Customer").
     * @param string    $viewFile Nama file di folder view/ tanpa ekstensi.
     * @param string[]  $fields   Daftar field form yang boleh diambil dari $_POST.
     */
    public function __construct(
        private BaseModel $model,
        private string $page,
        private string $label,
        private string $viewFile,
        private array $fields
    ) {
    }

    /** Titik masuk: memilih penanganan sesuai nilai ?action=. */
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

    // ------------------------------------------------------------------
    // Aksi tampilan (GET)
    // ------------------------------------------------------------------

    /** Menampilkan daftar data dengan pagination. */
    private function index(): void
    {
        $current   = max(1, (int) ($_GET['p'] ?? 1));
        $paginator = new Paginator($this->model->count(), PER_PAGE, $current);
        $rows      = $this->model->listPage($paginator->perPage(), $paginator->offset());

        $this->render([
            'mode' => 'list', 'rows' => $rows, 'paginator' => $paginator, 'item' => null,
        ]);
    }

    /** Menampilkan form (tambah bila $item null, ubah bila berisi data). */
    private function showForm(?array $item): void
    {
        $this->render(['mode' => 'form', 'item' => $item, 'rows' => [], 'paginator' => null]);
    }

    /** Memuat data yang akan diubah; bila tidak ada, kembali ke daftar. */
    private function showEdit(): void
    {
        $item = $this->model->find((int) ($_GET['id'] ?? 0));
        if ($item === null) {
            $this->notFound();
        }
        $this->showForm($item);
    }

    // ------------------------------------------------------------------
    // Aksi pengubah data (POST)
    // ------------------------------------------------------------------

    /** Menyimpan data baru. */
    private function store(): void
    {
        Csrf::validateRequest();
        $input = $this->collectInput();

        try {
            $this->model->create($input);
            Flash::success($this->label . ' berhasil ditambahkan.');
            Http::redirect(Http::url(['page' => $this->page]));
        } catch (ValidationException $e) {
            // Kembali ke form dengan input lama dan pesan error per field.
            Flash::withValidation($input, $e->getErrors());
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            error_log('Gagal menyimpan ' . $this->page . ': ' . $e->getMessage());
            Flash::withValidation($input, []);
            Flash::error('Data gagal disimpan karena terjadi kesalahan pada database.');
        }

        Http::redirect(Http::url(['page' => $this->page, 'action' => 'create']));
    }

    /** Memperbarui data yang sudah ada. */
    private function update(): void
    {
        Csrf::validateRequest();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->model->exists($id)) {
            $this->notFound();
        }
        $input = $this->collectInput();

        try {
            $this->model->update($id, $input);
            Flash::success($this->label . ' berhasil diperbarui.');
            Http::redirect(Http::url(['page' => $this->page]));
        } catch (ValidationException $e) {
            Flash::withValidation($input, $e->getErrors());
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            error_log('Gagal memperbarui ' . $this->page . ': ' . $e->getMessage());
            Flash::withValidation($input, []);
            Flash::error('Data gagal diperbarui karena terjadi kesalahan pada database.');
        }

        Http::redirect(Http::url(['page' => $this->page, 'action' => 'edit', 'id' => $id]));
    }

    /** Menghapus data; penghapusan ditolak bila data masih dipakai. */
    private function destroy(): void
    {
        Csrf::validateRequest();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->model->exists($id)) {
            $this->notFound();
        }

        try {
            $this->model->delete($id);
            Flash::success($this->label . ' berhasil dihapus.');
        } catch (DeleteRestrictedException $e) {
            // Pesan ramah pengguna, bukan error mentah database.
            Flash::error($e->getMessage());
        } catch (PDOException $e) {
            // Jaring pengaman bila foreign key tetap menolak.
            error_log('Gagal menghapus ' . $this->page . ': ' . $e->getMessage());
            Flash::error($this->label . ' tidak dapat dihapus karena masih terhubung dengan data lain.');
        }

        Http::redirect(Http::url(['page' => $this->page]));
    }

    // ------------------------------------------------------------------
    // Pembantu internal
    // ------------------------------------------------------------------

    /** Hanya mengambil field yang diizinkan dari $_POST (nilai non-string dikosongkan). */
    private function collectInput(): array
    {
        $input = [];
        foreach ($this->fields as $field) {
            $value = $_POST[$field] ?? '';
            $input[$field] = is_string($value) ? trim($value) : '';
        }
        return $input;
    }

    /** Aksi pengubah data hanya boleh lewat POST. */
    private function requirePost(): void
    {
        if (!Http::isPost()) {
            Http::redirect(Http::url(['page' => $this->page]));
        }
    }

    /** Data tidak ditemukan: beri pesan lalu kembali ke daftar. */
    private function notFound(): void
    {
        Flash::error($this->label . ' tidak ditemukan.');
        Http::redirect(Http::url(['page' => $this->page]));
    }

    /** Merangkai halaman: header + view entitas + footer. */
    private function render(array $data): void
    {
        $data['currentPage'] = $this->page;
        $data['label']       = $this->label;

        // Variabel di $data menjadi variabel lokal di dalam file view.
        extract($data, EXTR_SKIP);

        $viewDir = dirname(__DIR__, 2) . '/view/';
        require $viewDir . 'header.php';
        require $viewDir . $this->viewFile . '.php';
        require $viewDir . 'footer.php';
    }
}
