    document.addEventListener('DOMContentLoaded', function() {
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            question.addEventListener('click', () => {
                item.classList.toggle('active');
                
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                    }
                });
            });
        });
        
        const hash = window.location.hash;
        if (hash) {
            const targetItem = document.querySelector(hash);
            if (targetItem) {
                targetItem.classList.add('active');
                setTimeout(() => {
                    targetItem.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            }
        }
    });