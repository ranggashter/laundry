<?php
namespace App\Models;

class Layanan extends Model {
    protected string $table = 'layanan';

    public function getActive(): array {
        return $this->where('status', 'aktif', 'nama_layanan ASC');
    }
}

