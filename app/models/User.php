<?php
namespace App\Models;

class User extends Model {
    protected string $table = 'users';

    public function findByEmail(string $email): ?array {
        return $this->firstWhere('email', $email);
    }

    public function getPelangganList(): array {
        return $this->where('role', 'pelanggan', 'nama ASC');
    }
}

