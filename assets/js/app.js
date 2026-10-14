// Menampilkan perkiraan harga pesanan.
document.addEventListener('DOMContentLoaded', function () {
    const weight = document.querySelector('#berat');
    const service = document.querySelector('#layanan_id');
    const total = document.querySelector('#perkiraan-total');

    function updateTotal() {
        if (!weight || !service || !total) {
            return;
        }

        const option = service.selectedIndex >= 0 ? service.options[service.selectedIndex] : null;
        const price = option ? Number(option.getAttribute('data-price') || 0) : 0;
        const kilograms = Number(weight.value || 0);
        const amount = Math.round(price * kilograms);

        total.textContent = 'Rp ' + amount.toLocaleString('id-ID');
    }

    if (weight && service && total) {
        weight.addEventListener('input', updateTotal);
        service.addEventListener('change', updateTotal);
    }

    updateTotal();

    // Simulasi QR: cocokkan kode yang diketik dengan kode milik pelanggan.
    const scanButton = document.querySelector('#scan-pelanggan');
    if (scanButton) {
        scanButton.addEventListener('click', function () {
            const codeInput = document.querySelector('#kode-pelanggan');
            const customer = document.querySelector('#pelanggan_id');
            const message = document.querySelector('#scan-message');
            if (!codeInput || !customer || !message) {
                return;
            }

            const code = codeInput.value.trim();
            if (!code) {
                message.textContent = 'Silakan ketik kode QR pelanggan terlebih dahulu.';
                message.className = 'form-text text-warning';
                return;
            }

            let match = null;
            for (let i = 0; i < customer.options.length; i++) {
                if (customer.options[i].getAttribute('data-code') === code) {
                    match = customer.options[i];
                    break;
                }
            }

            if (match) {
                customer.value = match.value;
                message.textContent = 'Pelanggan ditemukan: ' + match.text;
                message.className = 'form-text text-success';
            } else {
                message.textContent = 'Kode tidak ditemukan. Pastikan kode QR pelanggan benar.';
                message.className = 'form-text text-danger';
            }
        });
    }

    // Perhitungan kalkulasi kasir: uang tunai, kembalian, dan status DP/Lunas
    const uangInput = document.querySelector('#uang_diterima');
    const statusBadge = document.querySelector('#calc-status-badge');
    const paidAmountElem = document.querySelector('#calc-paid-amount');
    const resultLabel = document.querySelector('#calc-result-label');
    const resultAmountElem = document.querySelector('#calc-result-amount');
    const noteElem = document.querySelector('#calc-note');

    function formatRupiah(val) {
        return 'Rp ' + Math.round(val).toLocaleString('id-ID');
    }

    function updatePaymentCalc() {
        if (!uangInput || !statusBadge || !paidAmountElem || !resultLabel || !resultAmountElem) {
            return;
        }

        const remaining = parseFloat(uangInput.getAttribute('data-remaining') || '0');
        const tendered = parseFloat(uangInput.value || '0');

        if (tendered <= 0) {
            statusBadge.textContent = 'Menunggu Input';
            statusBadge.className = 'badge-soft badge-soft-secondary';
            paidAmountElem.textContent = 'Rp 0';
            resultLabel.textContent = 'Sisa Tagihan:';
            resultAmountElem.textContent = formatRupiah(remaining);
            resultAmountElem.className = 'fs-5 fw-bold text-danger';
            if (noteElem) noteElem.textContent = 'Masukkan jumlah uang tunai yang diserahkan pelanggan.';
            return;
        }

        if (tendered >= remaining) {
            const change = tendered - remaining;
            statusBadge.textContent = change > 0 ? 'LUNAS (Uang Lebih)' : 'LUNAS (Uang Pas)';
            statusBadge.className = 'badge-soft badge-soft-success';
            paidAmountElem.textContent = formatRupiah(remaining);
            resultLabel.textContent = 'Kembalian:';
            resultAmountElem.textContent = formatRupiah(change);
            resultAmountElem.className = 'fs-5 fw-bold ' + (change > 0 ? 'text-primary' : 'text-success');
            if (noteElem) {
                noteElem.textContent = change > 0 
                    ? 'Uang lebih. Berikan kembalian ' + formatRupiah(change) + ' kepada pelanggan.' 
                    : 'Uang pas sesuai total sisa tagihan.';
            }
        } else {
            const sisaBaru = remaining - tendered;
            statusBadge.textContent = 'BELUM LUNAS (DP)';
            statusBadge.className = 'badge-soft badge-soft-warning';
            paidAmountElem.textContent = formatRupiah(tendered);
            resultLabel.textContent = 'Sisa Tagihan Belum Dibayar:';
            resultAmountElem.textContent = formatRupiah(sisaBaru);
            resultAmountElem.className = 'fs-5 fw-bold text-danger';
            if (noteElem) {
                noteElem.textContent = 'Uang kurang dari sisa tagihan. Dicatat sebagai Uang Muka (DP). Kurang ' + formatRupiah(sisaBaru) + '.';
            }
        }
    }

    if (uangInput) {
        uangInput.addEventListener('input', updatePaymentCalc);
        uangInput.addEventListener('change', updatePaymentCalc);

        // Tombol nominal cepat
        const quickButtons = document.querySelectorAll('.btn-quick-cash');
        quickButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const val = this.getAttribute('data-val');
                if (val) {
                    uangInput.value = val;
                    updatePaymentCalc();
                    uangInput.focus();
                }
            });
        });

        updatePaymentCalc();
    }
});
