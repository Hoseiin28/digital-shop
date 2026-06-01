    document.addEventListener('DOMContentLoaded', () => {
        const slides = document.querySelectorAll('.slide');
        const prevBtn = document.querySelector('.slider-control.prev');
        const nextBtn = document.querySelector('.slider-control.next');
        const dots = document.querySelectorAll('.dot');
        const progressBar = document.querySelector('.progress');
        let currentIndex = 0;
        const totalSlides = slides.length;
        const slideDuration = 6000;
        let slideInterval;
        let progressInterval;
        let progressWidth = 0;
      
        function resetProgress() {
            progressWidth = 0;
            progressBar.style.width = '0%';
        }
      
        function startProgress() {
            resetProgress();
            progressInterval = setInterval(() => {
                progressWidth += 100 / (slideDuration / 50);
                if (progressWidth >= 100) {
                    progressWidth = 100;
                    clearInterval(progressInterval);
                }
                progressBar.style.width = progressWidth + '%';
            }, 50);
        }
      
        function goToSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
                dots[i].classList.toggle('active', i === index);
            });
            currentIndex = index;
            resetProgress();
            startProgress();
        }
      
        function nextSlide() {
            let nextIndex = (currentIndex + 1) % totalSlides;
            goToSlide(nextIndex);
        }
      
        function prevSlide() {
            let prevIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            goToSlide(prevIndex);
        }
      
        function startAutoSlide() {
            slideInterval = setInterval(nextSlide, slideDuration);
            startProgress();
        }
      
        function stopAutoSlide() {
            clearInterval(slideInterval);
            clearInterval(progressInterval);
        }
      
        prevBtn.addEventListener('click', () => {
            prevSlide();
            stopAutoSlide();
            startAutoSlide();
        });
      
        nextBtn.addEventListener('click', () => {
            nextSlide();
            stopAutoSlide();
            startAutoSlide();
        });
      
        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                goToSlide(+dot.dataset.slide);
                stopAutoSlide();
                startAutoSlide();
            });
        });

        const heroSlider = document.querySelector('.hero-slider');
        heroSlider.addEventListener('mouseenter', stopAutoSlide);
        heroSlider.addEventListener('mouseleave', startAutoSlide);
      
        document.addEventListener('keydown', (e) => {
            if (e.key === "ArrowRight") {
                nextSlide();
                stopAutoSlide();
                startAutoSlide();
            } else if (e.key === "ArrowLeft") {
                prevSlide();
                stopAutoSlide();
                startAutoSlide();
            }
        });

        startAutoSlide();
      
        goToSlide(0);
    });

    function showLoginMessage() {
        document.getElementById('login-message-container').classList.add('show');
    }

    function hideLoginMessage() {
        document.getElementById('login-message-container').classList.remove('show');
    }


        document.querySelectorAll('.quick-view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            window.location.href = `public/product-details.php?id=${productId}`;
        });
    });


    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationMessage = document.getElementById('notification-message');
        
        notificationMessage.textContent = message;
        notification.className = 'notification';
        
        switch (type) {
            case 'success':
                notification.style.backgroundColor = '#4CAF50';
                break;
            case 'error':
                notification.style.backgroundColor = '#f44336';
                break;
            case 'warning':
                notification.style.backgroundColor = '#ff9800';
                break;
            default:
                notification.style.backgroundColor = '#4CAF50';
        }
        
        notification.classList.add('show');
        
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }

    setTimeout(() => {
        document.getElementById('chatPopup').classList.add('show');
    }, 10000);

    document.getElementById('closeChat').addEventListener('click', () => {
        document.getElementById('chatPopup').classList.remove('show');
    });


function updateCompareList(checkbox) {
    const productId = checkbox.dataset.productId;
    const isChecked = checkbox.checked;

    if (isChecked) {
        fetch('public/add_to_compare.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('محصول به لیست مقایسه اضافه شد', 'success');
                updateCompareUI();
            } else {
                checkbox.checked = false;
                showNotification(data.message || 'خطا در افزودن به لیست مقایسه', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            checkbox.checked = false;
            showNotification('خطا در ارتباط با سرور', 'error');
        });
    } else {
        fetch('public/remove_from_compare.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('محصول از لیست مقایسه حذف شد', 'warning');
                updateCompareUI();
            } else {
                checkbox.checked = true;
                showNotification(data.message || 'خطا در حذف از لیست مقایسه', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            checkbox.checked = true;
            showNotification('خطا در ارتباط با سرور', 'error');
        });
    }
}

function updateCompareUI() {
    fetch('public/get_compare_list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const compareList = document.getElementById('compare-products-list');
            const compareButton = document.getElementById('compare-button');
            const clearCompareBtn = document.getElementById('clear-compare');
            
            compareList.innerHTML = '';
            data.products.forEach(product => {
                const productElement = document.createElement('div');
                productElement.className = 'compare-product-item';
                productElement.innerHTML = `
                    <img src="public/${product.image_url}" alt="${product.name}">
                    <span>${product.name}</span>
                    <button onclick="removeFromCompare(${product.id})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                compareList.appendChild(productElement);
            });

            compareButton.disabled = data.count < 2;
            
            document.querySelectorAll('.compare-checkbox').forEach(checkbox => {
                const productId = checkbox.dataset.productId;
                checkbox.checked = data.products.some(p => p.id == productId);
            });
            
            const compareCountElements = document.querySelectorAll('.compare-count, .mobile-compare-count');
            compareCountElements.forEach(el => {
                if (data.count > 0) {
                    el.textContent = data.count;
                    el.style.display = 'inline-block';
                } else {
                    el.style.display = 'none';
                }
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function removeFromCompare(productId) {
    fetch('public/remove_from_compare.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('محصول از لیست مقایسه حذف شد', 'warning');
            updateCompareUI();
        } else {
            showNotification(data.message || 'خطا در حذف از لیست مقایسه', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    });
}

function clearCompareList() {
    if (confirm('آیا می‌خواهید تمام محصولات را از لیست مقایسه حذف کنید؟')) {
        fetch('public/clear_comparison.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('لیست مقایسه پاک شد', 'warning');
                updateCompareUI();
            } else {
                showNotification(data.message || 'خطا در پاک کردن لیست مقایسه', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
        });
    }
}

function redirectToCompare() {
    fetch('public/get_compare_list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.count >= 2) {
            const productIds = data.products.map(p => p.id).join(',');
            window.location.href = `public/compare.php?products=${productIds}`;
        } else {
            showNotification('برای مقایسه حداقل نیاز به 2 محصول دارید', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateCompareUI();
});