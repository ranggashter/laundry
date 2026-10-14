<?php
namespace App\Middleware;

class GuestMiddleware {
    public function handle(): void {
        if (is_authenticated()) {
            redirect('/dashboard');
        }
    }
}

