<?php
namespace App\Models;

class Pesanan extends Model {
    protected string $table = 'pesanan';

    public function getAllWithDetails(?int $pelangganId = null): array {
        $sql = "SELECT p.*, 
                       u.nama AS nama_pelanggan, 
                       u.no_hp AS no_hp_pelanggan,
                       k.nama AS nama_kasir,
                       s.tanggal AS slot_tanggal,
                       s.jam_mulai AS slot_jam_mulai,
                       s.jam_selesai AS slot_jam_selesai,
                       (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.pesanan_id = p.id) as total_item
                FROM `{$this->table}` p
                LEFT JOIN users u ON p.pelanggan_id = u.id
                LEFT JOIN users k ON p.kasir_id = k.id
                LEFT JOIN slot_jadwal s ON p.slot_jadwal_id = s.id";

        $params = [];
        if ($pelangganId !== null) {
            $sql .= " WHERE p.pelanggan_id = :pelanggan_id";
            $params['pelanggan_id'] = $pelangganId;
        }

        $sql .= " ORDER BY p.id DESC";

        return $this->rawQuery($sql, $params);
    }

    public function getDetailById(int $id): ?array {
        $sql = "SELECT p.*, 
                       u.nama AS nama_pelanggan, 
                       u.email AS email_pelanggan,
                       u.no_hp AS no_hp_pelanggan,
                       u.alamat AS alamat_pelanggan,
                       k.nama AS nama_kasir,
                       s.tanggal AS slot_tanggal,
                       s.jam_mulai AS slot_jam_mulai,
                       s.jam_selesai AS slot_jam_selesai,
                       n.nomor_nota,
                       n.tanggal_terbit AS nota_terbit
                FROM `{$this->table}` p
                LEFT JOIN users u ON p.pelanggan_id = u.id
                LEFT JOIN users k ON p.kasir_id = k.id
                LEFT JOIN slot_jadwal s ON p.slot_jadwal_id = s.id
                LEFT JOIN nota n ON n.pesanan_id = p.id
                WHERE p.id = :id
                LIMIT 1";

        return $this->rawFirst($sql, ['id' => $id]);
    }

    public function getStatistics(?int $pelangganId = null): array {
        $where = $pelangganId ? "WHERE pelanggan_id = {$pelangganId}" : "";

        $totalPesanan = (int)($this->rawFirst("SELECT COUNT(*) as c FROM pesanan {$where}")['c'] ?? 0);
        $dalamProses = (int)($this->rawFirst("SELECT COUNT(*) as c FROM pesanan WHERE status_cucian NOT IN ('selesai', 'dibatalkan') " . ($pelangganId ? "AND pelanggan_id = {$pelangganId}" : ""))['c'] ?? 0);
        $selesai = (int)($this->rawFirst("SELECT COUNT(*) as c FROM pesanan WHERE status_cucian = 'selesai' " . ($pelangganId ? "AND pelanggan_id = {$pelangganId}" : ""))['c'] ?? 0);
        $pendapatan = (float)($this->rawFirst("SELECT SUM(total_harga) as s FROM pesanan WHERE status_pembayaran = 'lunas' " . ($pelangganId ? "AND pelanggan_id = {$pelangganId}" : ""))['s'] ?? 0);

        return [
            'total_pesanan' => $totalPesanan,
            'dalam_proses'  => $dalamProses,
            'selesai'       => $selesai,
            'pendapatan'    => $pendapatan
        ];
    }
}

