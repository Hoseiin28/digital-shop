        function selectPaymentMethod(method) {
            document.querySelectorAll('.bank-card').forEach(card => {
                card.classList.remove('selected');
            });

            const selectedCard = document.querySelector(`[onclick="selectPaymentMethod('${method}')"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
                const radioInput = selectedCard.querySelector('input[type="radio"]');
                if (radioInput) {
                    radioInput.checked = true;
                } else {
                    const newInput = document.createElement('input');
                    newInput.type = 'radio';
                    newInput.name = 'payment_method';
                    newInput.value = method === 'saman' ? 'saman' : 'cash_on_delivery';
                    newInput.id = `${method}-payment`;
                    newInput.style.display = 'none';
                    selectedCard.appendChild(newInput);
                    newInput.checked = true;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            selectPaymentMethod('saman');
        });

        document.querySelectorAll('.bank-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.classList.contains('selected')) {
                    this.style.transform = 'translateY(-5px)';
                }
            });

            card.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected')) {
                    this.style.transform = '';
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.bank-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.display = 'block';

                    setTimeout(() => {
                        card.style.transition = 'all 0.5s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    