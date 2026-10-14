// assets/js/app.js - Modern Frontend Interactivity for LaundryApp
document.addEventListener('DOMContentLoaded', function () {
    // ---------------------------------------------------------
    // 1. Password Visibility Toggle on Login Page
    // ---------------------------------------------------------
    const togglePasswordBtn = document.querySelector('#togglePasswordBtn');
    const passwordInput = document.querySelector('#password');
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            const icon = togglePasswordBtn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    }

    // ---------------------------------------------------------
    // 2. Demo Account Quick-Fill on Login Page
    // ---------------------------------------------------------
    const demoButtons = document.querySelectorAll('.btn-demo-fill');
    const usernameInput = document.querySelector('#username');
    if (demoButtons.length > 0 && usernameInput && passwordInput) {
        demoButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const user = this.getAttribute('data-user') || '';
                const pass = this.getAttribute('data-pass') || 'password';
                usernameInput.value = user;
                passwordInput.value = pass;
                usernameInput.focus();
            });
        });
    }

    // ---------------------------------------------------------
    // 3. Dynamic Cost Calculator (kasir/buat_pesanan.php)
    // ---------------------------------------------------------
    const weightInput = document.querySelector('#berat');
    const serviceSelect = document.querySelector('#layanan_id');
    const totalPreview = document.querySelector('#perkiraan-total');
    const previewSummary = document.querySelector('#preview-detail-text');

    function calculateOrderTotal() {
        if (!weightInput || !serviceSelect || !totalPreview) {
            return;
        }

        const selectedOption = serviceSelect.selectedIndex >= 0 ? serviceSelect.options[serviceSelect.selectedIndex] : null;
        const pricePerKg = selectedOption ? Number(selectedOption.getAttribute('data-price') || 0) : 0;
        const weight = Number(weightInput.value || 0);
        const total = Math.round(pricePerKg * weight);

        totalPreview.textContent = 'Rp ' + total.toLocaleString('id-ID');
        if (previewSummary) {
            if (weight > 0 && pricePerKg > 0) {
                previewSummary.textContent = `${weight} kg × Rp ${pricePerKg.toLocaleString('id-ID')} /kg`;
            } else {
                previewSummary.textContent = 'Pilih paket dan tentukan berat';
            }
        }
    }

    if (weightInput && serviceSelect) {
        weightInput.addEventListener('input', calculateOrderTotal);
        serviceSelect.addEventListener('change', calculateOrderTotal);
        calculateOrderTotal();
    }

    // Quick Weight Increment Buttons (+1, +3, +5 kg)
    const quickWeightBtns = document.querySelectorAll('.btn-quick-weight');
    if (quickWeightBtns.length > 0 && weightInput) {
        quickWeightBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const add = Number(this.getAttribute('data-add') || 0);
                const current = Number(weightInput.value || 0);
                const nextVal = Math.round((current + add) * 10) / 10;
                weightInput.value = nextVal > 0 ? nextVal : 1;
                calculateOrderTotal();
            });
        });
    }

    // ---------------------------------------------------------
    // 4. Customer QR Search Simulation (kasir/buat_pesanan.php)
    // ---------------------------------------------------------
    const scanButton = document.querySelector('#scan-pelanggan');
    const codeInput = document.querySelector('#kode-pelanggan');
    const customerSelect = document.querySelector('#pelanggan_id');
    const scanMessage = document.querySelector('#scan-message');

    function triggerScanMatch() {
        if (!codeInput || !customerSelect || !scanMessage) {
            return;
        }

        const code = codeInput.value.trim().toUpperCase();
        if (!code) {
            scanMessage.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i>Ketik kode QR pelanggan terlebih dahulu.</span>';
            return;
        }

        let match = null;
        for (let i = 0; i < customerSelect.options.length; i++) {
            const optCode = (customerSelect.options[i].getAttribute('data-code') || '').toUpperCase();
            if (optCode === code) {
                match = customerSelect.options[i];
                break;
            }
        }

        if (match) {
            customerSelect.value = match.value;
            scanMessage.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Pelanggan ditemukan: <strong>${match.text}</strong></span>`;
        } else {
            scanMessage.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Kode QR tidak ditemukan di daftar pelanggan aktif.</span>';
        }
    }

    if (scanButton) {
        scanButton.addEventListener('click', triggerScanMatch);
    }
    if (codeInput) {
        codeInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                triggerScanMatch();
            }
        });
    }

    // ---------------------------------------------------------
    // 5. Quick Pay Presets on Cashier Payment (kasir/pembayaran.php)
    // ---------------------------------------------------------
    const quickPayBtns = document.querySelectorAll('.btn-quick-pay');
    const paymentAmountInput = document.querySelector('#jumlah');
    if (quickPayBtns.length > 0 && paymentAmountInput) {
        quickPayBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const amount = this.getAttribute('data-amount');
                if (amount) {
                    paymentAmountInput.value = amount;
                    paymentAmountInput.focus();
                }
            });
        });
    }

    // ---------------------------------------------------------
    // 6. Instant Table Search Filter (Client-side)
    // ---------------------------------------------------------
    const tableSearchInputs = document.querySelectorAll('[data-table-search]');
    tableSearchInputs.forEach(input => {
        const targetTableSelector = input.getAttribute('data-table-search');
        const targetTable = document.querySelector(targetTableSelector);
        if (!targetTable) return;

        input.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    // ---------------------------------------------------------
    // 7. Clipboard Copy Helper
    // ---------------------------------------------------------
    const copyBtns = document.querySelectorAll('[data-copy-target]');
    copyBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-copy-target');
            const targetEl = document.querySelector(targetId);
            if (!targetEl) return;
            const textToCopy = targetEl.value || targetEl.textContent || '';
            navigator.clipboard.writeText(textToCopy.trim()).then(() => {
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check2"></i> Disalin!';
                this.classList.add('btn-success');
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                    this.classList.remove('btn-success');
                }, 2000);
            });
        });
    });
});
