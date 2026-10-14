<?php
namespace App\Models;

class Nota extends Model {
    protected string $table = 'nota';

    public function findByPesananId(int $pesananId): ?array {
        return $this->firstWhere('pesanan_id', $pesananId);
    }
}

