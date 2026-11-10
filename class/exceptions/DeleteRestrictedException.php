<?php
/**
 * DeleteRestrictedException
 *
 * Dilempar ketika data tidak boleh dihapus karena masih dipakai data lain
 * (mis. customer yang masih punya booking). Pesannya ramah pengguna sehingga
 * error mentah dari database tidak pernah ditampilkan.
 */
class DeleteRestrictedException extends Exception
{
    // Tidak butuh properti tambahan: seluruh informasi ada di pesan exception.
}
