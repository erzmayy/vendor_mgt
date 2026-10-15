<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class FormulirRepository
{
    public function fieldAktif(): array
    {
        return Database::prototype()->query(
            'SELECT * FROM tbl_field_formulir WHERE aktif = 1 ORDER BY urutan, id_field'
        )->fetchAll();
    }
}
