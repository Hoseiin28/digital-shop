document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-number');
    const speed = 200;
    
    function animateCounters() {
        counters.forEach(counter => {
            const target = +counter.getAttribute('data-count');
            const count = +counter.innerText;
            const increment = target / speed;
            
            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(animateCounters, 1);
            } else {
                counter.innerText = target + '+';
            }
        });
    }
    window.addEventListener('scroll', function() {
        const heroStats = document.querySelector('.hero-stats');
        const position = heroStats.getBoundingClientRect().top;
        const screenPosition = window.innerHeight / 1.3;
        
        if (position < screenPosition) {
            animateCounters();
        }
    });

    const timelineItems = document.querySelectorAll('.timeline-item');
    
    function animateTimeline() {
        timelineItems.forEach(item => {
            const itemTop = item.getBoundingClientRect().top;
            const screenPosition = window.innerHeight / 1.3;
            
            if (itemTop < screenPosition) {
                item.style.animation = 'fadeInUp 0.5s ease forwards';
            }
        });
    }
    
    window.addEventListener('scroll', animateTimeline);
    animateTimeline();
});