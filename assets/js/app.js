// Menampilkan perkiraan harga pesanan.
document.addEventListener('DOMContentLoaded', function () {
    const weight = document.querySelector('#berat');
    const service = document.querySelector('#layanan_id');
    const total = document.querySelector('#perkiraan-total');

    function updateTotal() {
        if (!weight || !service || !total) {
            return;
        }

        const option = service.options[service.selectedIndex];
        const price = Number(option.getAttribute('data-price') || 0);
        const kilograms = Number(weight.value || 0);
        const amount = price * kilograms;

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
            const code = codeInput.value.trim();
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
});
