// assets/js/app.js - Modern Frontend Interactivity for LaundryApp
document.addEventListener('DOMContentLoaded', function () {
    const customerQrCode = document.querySelector('#customerQrCode');
    const transactionQrCode = document.querySelector('#transactionQrCode');
    if (window.QRCode) {
        [customerQrCode, transactionQrCode].filter(Boolean).forEach(function (qrElement) {
            new QRCode(qrElement, {
                text: qrElement.dataset.qrCode,
                width: qrElement === customerQrCode ? 176 : 144,
                height: qrElement === customerQrCode ? 176 : 144,
                colorDark: '#172033',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    }

    document.querySelectorAll('[data-download-qr]').forEach(function (button) {
        button.addEventListener('click', function () {
            const qrElement = document.querySelector(button.dataset.downloadQr);
            const canvas = qrElement && qrElement.querySelector('canvas');
            const image = qrElement && qrElement.querySelector('img');
            const imageUrl = canvas ? canvas.toDataURL('image/png') : (image ? image.src : '');
            if (!imageUrl) {
                return;
            }

            const downloadLink = document.createElement('a');
            downloadLink.href = imageUrl;
            downloadLink.download = (button.dataset.qrFilename || 'qr-code') + '.png';
            downloadLink.click();
        });
    });

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
    // ---------------------------------------------------------
    // 3. Demo Account Quick-Fill on Login Page
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
    // 4. Dynamic Cost Calculator (kasir/buat_pesanan.php)
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
    // 5. Customer QR Search Simulation (kasir/buat_pesanan.php)
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
            scanMessage.className = 'small text-warning';
            scanMessage.textContent = 'Ketik atau scan kode QR pelanggan terlebih dahulu.';
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
            scanMessage.className = 'small text-success fw-semibold';
            scanMessage.textContent = 'Pelanggan ditemukan: ' + match.text;
        } else {
            scanMessage.className = 'small text-danger';
            scanMessage.textContent = 'Kode QR tidak ditemukan di daftar pelanggan.';
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

    function attachQrCamera(buttonSelector, readerSelector, input, message, onScan) {
        const button = document.querySelector(buttonSelector);
        const reader = document.querySelector(readerSelector);
        if (!button || !reader || !input) {
            return;
        }

        const label = button.querySelector('[data-scan-label]');
        let scanner = null;
        let scanning = false;

        button.addEventListener('click', async function () {
            if (scanning) {
                scanning = false;
                try {
                    await scanner.stop();
                    await scanner.clear();
                } catch (error) {
                    // Kamera mungkin sudah berhenti setelah hasil scan ditemukan.
                }
                reader.hidden = true;
                button.disabled = false;
                if (label) label.textContent = 'Scan Kamera';
                return;
            }

            if (!window.Html5Qrcode) {
                if (message) message.textContent = 'Pemindai kamera gagal dimuat. Masukkan kode secara manual.';
                return;
            }

            button.disabled = true;
            reader.hidden = false;
            if (message) message.textContent = 'Meminta izin kamera...';

            try {
                scanner = new Html5Qrcode(reader.id);
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 220, height: 220 } },
                    async function (decodedText) {
                        if (!scanning) return;
                        scanning = false;
                        input.value = decodedText.trim();
                        try {
                            await scanner.stop();
                            await scanner.clear();
                        } catch (error) {
                            // Lanjutkan dengan hasil scan walau kamera gagal berhenti.
                        }
                        reader.hidden = true;
                        button.disabled = false;
                        if (label) label.textContent = 'Scan Kamera';
                        onScan(decodedText.trim());
                    },
                    function () {}
                );
                scanning = true;
                button.disabled = false;
                if (label) label.textContent = 'Tutup Kamera';
                if (message) message.textContent = 'Arahkan kamera ke QR pelanggan atau nota.';
            } catch (error) {
                scanning = false;
                reader.hidden = true;
                button.disabled = false;
                if (label) label.textContent = 'Scan Kamera';
                if (message) message.textContent = 'Kamera tidak dapat dibuka. Izinkan akses kamera atau masukkan kode manual.';
            }
        });
    }

    attachQrCamera('#scan-camera-pelanggan', '#qr-reader-pelanggan', codeInput, scanMessage, triggerScanMatch);

    const trackingInput = document.querySelector('#tracking-code');
    const trackingForm = document.querySelector('#tracking-form');
    attachQrCamera(
        '#scan-camera-tracking',
        '#qr-reader-tracking',
        trackingInput,
        document.querySelector('#tracking-scan-message'),
        function () {
            if (trackingForm) trackingForm.requestSubmit();
        }
    );

    // ---------------------------------------------------------
    // 6. Quick Pay Presets on Cashier Payment (kasir/pembayaran.php)
    // ---------------------------------------------------------
    const quickPayBtns = document.querySelectorAll('.btn-quick-pay');
    const paymentAmountInput = document.querySelector('#uang_diterima, #jumlah');
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
    // 7. Instant Table Search Filter (Client-side)
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
    // 8. Clipboard Copy Helper
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
