        let seconds = 10;
        const countdownElement = document.getElementById('countdown');
        
        const countdownInterval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '../index.php';
            }
        }, 1000);
        
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                clearInterval(countdownInterval);
            });
        });