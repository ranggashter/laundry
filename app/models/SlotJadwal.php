<?php
namespace App\Models;

class SlotJadwal extends Model {
    protected string $table = 'slot_jadwal';

    public function getAvailableSlots(): array {
        return $this->rawQuery(
            "SELECT * FROM `{$this->table}` 
             WHERE status = 'tersedia' 
             AND id NOT IN (SELECT slot_jadwal_id FROM pesanan WHERE slot_jadwal_id IS NOT NULL)
             ORDER BY tanggal ASC, jam_mulai ASC"
        );
    }
}

