// Toggle Sidebar Mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Modal Functions
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal when clicking outside
window.onclick = function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
}

// Auto hide alert after 4 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 4000);

// Format Rupiah
function formatRupiah(angka) {
    if (!angka) return 'Rp 0';
    let number = typeof angka === 'string' ? parseInt(angka.replace(/[^0-9]/g, '')) : angka;
    return 'Rp ' + number.toLocaleString('id-ID');
}

// Print Function
function printStruk(url) {
    let printWindow = window.open(url, '_blank', 'width=400,height=600');
    printWindow.onload = function() {
        printWindow.print();
    };
}

function printInvoice(url) {
    let printWindow = window.open(url, '_blank', 'width=800,height=600');
    printWindow.onload = function() {
        printWindow.print();
    };
}

// Confirm Delete
function confirmDelete(message) {
    return confirm(message || 'Apakah Anda yakin ingin menghapus data ini?');
}

document.querySelectorAll('[data-inventory-form]').forEach(form => {
    const lines = form.querySelector('.inventory-lines');
    const total = form.querySelector('.inventory-total');
    const template = document.getElementById('inventoryLineTemplate');
    const method = form.querySelector('.payment-method');
    const term = form.querySelector('.installment-term');
    const options = form.querySelectorAll('.installment-options');
    const summary = form.querySelector('.installment-summary');
    const installmentTotal = form.querySelector('.installment-total');
    const monthly = form.querySelector('.installment-monthly');
    const paymentLabel = form.querySelector('.payment-label');

    function updateTotal() {
        let amount = 0;
        lines.querySelectorAll('.inventory-line').forEach(line => {
            const option = line.querySelector('.inventory-item').selectedOptions[0];
            const quantity = Number(line.querySelector('.inventory-quantity').value) || 0;
            amount += (Number(option.dataset.price) || 0) * quantity;
        });
        total.value = amount;
        if (method) {
            const isInstallment = method.value === 'cicilan';
            options.forEach(option => option.hidden = !isInstallment);
            if (summary) summary.hidden = !isInstallment;
            if (paymentLabel) paymentLabel.textContent = isInstallment ? 'Uang Muka' : 'Bayar';
            if (summary && isInstallment && term) {
                const interest = Number(term.selectedOptions[0].dataset.interest) || 0;
                const due = Math.round(amount * (1 + interest / 100) * 100) / 100;
                installmentTotal.value = due;
                monthly.textContent = `Bunga ${interest}% · Cicilan sekitar Rp ${(due / Number(term.value)).toLocaleString('id-ID', { maximumFractionDigits: 2 })} per bulan`;
            }
        }
    }

    form.addEventListener('input', updateTotal);
    form.addEventListener('change', updateTotal);
    form.addEventListener('click', event => {
        if (event.target.closest('.inventory-add')) {
            lines.append(template.content.cloneNode(true));
            updateTotal();
        }
        if (event.target.closest('.inventory-remove')) {
            const rows = lines.querySelectorAll('.inventory-line');
            if (rows.length > 1) event.target.closest('.inventory-line').remove();
            updateTotal();
        }
    });

    form.addEventListener('submit', event => {
        for (const line of lines.querySelectorAll('.inventory-line')) {
            const select = line.querySelector('.inventory-item');
            const quantity = line.querySelector('.inventory-quantity');
            const stock = Number(select.selectedOptions[0].dataset.stock);
            if (select.selectedOptions[0].value && Number.isFinite(stock) && Number(quantity.value) > stock) {
                event.preventDefault();
                quantity.setCustomValidity(`Stok tersedia hanya ${stock}.`);
                quantity.reportValidity();
                return;
            }
            quantity.setCustomValidity('');
        }
    });
});

document.querySelectorAll('.open-payment-modal').forEach(button => {
    button.addEventListener('click', () => {
        const modal = document.getElementById('modalPembayaran');
        const id = modal.querySelector('.payment-transaction-id');
        const type = modal.querySelector('.payment-transaction-type');
        const name = modal.querySelector('.payment-transaction-name');
        const balance = modal.querySelector('.payment-balance');
        const amount = modal.querySelector('.payment-entry-amount');
        id.value = button.dataset.id;
        if (type) type.value = button.dataset.type || '';
        name.textContent = button.dataset.name;
        balance.textContent = `Rp ${Number(button.dataset.balance).toLocaleString('id-ID')}`;
        amount.max = button.dataset.balance;
        amount.value = '';
        openModal('modalPembayaran');
    });
});
