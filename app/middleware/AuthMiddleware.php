<?php
namespace App\Middleware;

class AuthMiddleware {
    public function handle(): void {
        if (!is_authenticated()) {
            flash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
            redirect('/login');
        }
    }
}

