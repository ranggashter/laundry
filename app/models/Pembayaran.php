<?php
namespace App\Models;

class Pembayaran extends Model {
    protected string $table = 'pembayaran';

    public function getByPesananId(int $pesananId): array {
        return $this->where('pesanan_id', $pesananId, 'id DESC');
    }

    public function getPendingPayments(): array {
        $sql = "SELECT pb.*, 
                       ps.kode_pesanan,
                       ps.total_harga,
                       u.nama AS nama_pelanggan,
                       u.no_hp AS no_hp_pelanggan
                FROM `{$this->table}` pb
                JOIN pesanan ps ON pb.pesanan_id = ps.id
                JOIN users u ON ps.pelanggan_id = u.id
                WHERE pb.status = 'menunggu'
                ORDER BY pb.id DESC";
        return $this->rawQuery($sql);
    }

    public function countPending(): int {
        return (int)($this->rawFirst("SELECT COUNT(*) as c FROM `{$this->table}` WHERE status = 'menunggu'")['c'] ?? 0);
    }
}
