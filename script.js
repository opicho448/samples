document.addEventListener('DOMContentLoaded', function() {
    const ticketSelect = document.querySelector('#ticket_type');
    const priceField = document.querySelector('#ticket_price');
    const paymentMethodSelect = document.querySelector('#payment_method');
    const paymentProofFields = document.querySelector('#payment_proof_fields');

    function updatePaymentProofVisibility() {
        if (!paymentProofFields || !ticketSelect || !paymentMethodSelect) {
            return;
        }
        const selected = ticketSelect.options[ticketSelect.selectedIndex];
        const price = parseFloat(selected.dataset.price || '0');
        const isMobileMoney = paymentMethodSelect.value === 'Mobile Money';
        paymentProofFields.style.display = price > 0 && isMobileMoney ? 'block' : 'none';
    }

    if (ticketSelect && priceField) {
        function updatePrice() {
            const selected = ticketSelect.options[ticketSelect.selectedIndex];
            priceField.textContent = selected.dataset.price ? '$' + selected.dataset.price : 'Free';
            updatePaymentProofVisibility();
        }
        updatePrice();
        ticketSelect.addEventListener('change', updatePrice);
    }

    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', updatePaymentProofVisibility);
        updatePaymentProofVisibility();
    }
});

// Marquee pause on hover
document.addEventListener('DOMContentLoaded', function() {
    const marquee = document.querySelector('.marquee p');
    if (!marquee) return;
    marquee.addEventListener('mouseenter', () => marquee.style.animationPlayState = 'paused');
    marquee.addEventListener('mouseleave', () => marquee.style.animationPlayState = 'running');
});
