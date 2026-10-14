<?php
namespace App\Models;

class DetailPesanan extends Model {
    protected string $table = 'detail_pesanan';

    public function getByPesananId(int $pesananId): array {
        return $this->where('pesanan_id', $pesananId);
    }
}

