<?php
namespace App\Controllers;

use App\Models\Pesanan;
use App\Models\Layanan;
use App\Models\User;

class DashboardController {
    private Pesanan $pesananModel;
    private Layanan $layananModel;
    private User $userModel;

    public function __construct() {
        $this->pesananModel = new Pesanan();
        $this->layananModel = new Layanan();
        $this->userModel = new User();
    }

    public function index(): void {
        $user = auth_user();
        $pelangganId = ($user['role'] === 'pelanggan') ? (int)$user['id'] : null;

        $stats = $this->pesananModel->getStatistics($pelangganId);
        $totalPelanggan = ($user['role'] !== 'pelanggan') ? $this->userModel->count("role = 'pelanggan'") : 0;
        $totalLayanan = $this->layananModel->count("status = 'aktif'");
        $recentOrders = $this->pesananModel->getAllWithDetails($pelangganId);
        $recentOrders = array_slice($recentOrders, 0, 5);

        view('dashboard.index', [
            'title'          => 'Dashboard - ' . APP_NAME,
            'user'           => $user,
            'stats'          => $stats,
            'totalPelanggan' => $totalPelanggan,
            'totalLayanan'   => $totalLayanan,
            'recentOrders'   => $recentOrders,
        ]);
    }
}

