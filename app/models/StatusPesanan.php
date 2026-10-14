<?php
namespace App\Models;

class StatusPesanan extends Model {
    protected string $table = 'status_pesanan';

    public function getHistoryByPesananId(int $pesananId): array {
        $sql = "SELECT sp.*, u.nama AS diubah_oleh_nama
                FROM `{$this->table}` sp
                LEFT JOIN users u ON sp.diubah_oleh = u.id
                WHERE sp.pesanan_id = :pesanan_id
                ORDER BY sp.id ASC";
        return $this->rawQuery($sql, ['pesanan_id' => $pesananId]);
    }
}

