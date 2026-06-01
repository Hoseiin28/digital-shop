        function activateTabFromHash() {
            const hash = window.location.hash;
            if (hash) {
                const tabTrigger = document.querySelector(`a[href="${hash}"]`);
                if (tabTrigger) {
                    document.querySelectorAll('.nav-link.active').forEach(el => {
                        el.classList.remove('active');
                    });

                    document.querySelectorAll('.tab-pane.active').forEach(el => {
                        el.classList.remove('active', 'show');
                    });

                    tabTrigger.classList.add('active');
                    const tabPane = document.querySelector(hash);
                    if (tabPane) {
                        tabPane.classList.add('active', 'show');
                    }

                    setTimeout(() => {
                        tabPane.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }, 100);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                const tabTrigger = new bootstrap.Tab(document.querySelector(activeTab));
                tabTrigger.show();
            }

            const tabTriggers = document.querySelectorAll('a[data-bs-toggle="tab"]');
            tabTriggers.forEach(tabTrigger => {
                tabTrigger.addEventListener('click', function() {
                    localStorage.setItem('activeTab', this.getAttribute('href'));
                });
            });

            const animateOnScroll = () => {
                const elements = document.querySelectorAll('.animate-fade-in');
                elements.forEach(element => {
                    const elementPosition = element.getBoundingClientRect().top;
                    const screenPosition = window.innerHeight / 1.3;

                    if (elementPosition < screenPosition) {
                        element.classList.add('animate__fadeInUp');
                    }
                });
            };

            window.addEventListener('scroll', animateOnScroll);
            animateOnScroll();

            activateTabFromHash();

            window.addEventListener('hashchange', activateTabFromHash);
        });

        function removeFavorite(button, productId) {
            Swal.fire({
                title: 'آیا مطمئن هستید؟',
                text: 'این محصول از لیست علاقه‌مندی‌های شما حذف خواهد شد',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'بله، حذف شود',
                cancelButtonText: 'انصراف'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('remove-favorite.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `product_id=${encodeURIComponent(productId)}`
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('خطا در ارتباط با سرور');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                button.closest('.col-md-4').classList.add('animate__animated', 'animate__fadeOut');
                                setTimeout(() => {
                                    button.closest('.col-md-4').remove();
                                    updateFavoritesCount();
                                }, 300);

                                Swal.fire(
                                    'حذف شد!',
                                    'محصول از لیست علاقه‌مندی‌ها حذف شد.',
                                    'success'
                                );
                            } else {
                                Swal.fire(
                                    'خطا!',
                                    data.message || 'خطا در حذف محصول',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'خطا!',
                                'خطا در ارتباط با سرور: ' + error.message,
                                'error'
                            );
                        });
                }
            });
        }

        function updateFavoritesCount() {
            const badge = document.querySelector('#favorites-count');
            if (!badge) return;

            const currentCount = parseInt(badge.textContent);
            const newCount = currentCount - 1;
            badge.textContent = newCount >= 0 ? newCount : 0;

            if (newCount <= 0) {
                const favoritesTab = document.getElementById('favorites');
                favoritesTab.innerHTML = `
                    <div class="profile-card">
                        <div class="card-header">
                            <i class="bi bi-heart-fill"></i> محصولات مورد علاقه
                        </div>
                        <div class="card-body">
                            <div class="empty-state text-center py-5">
                                <div class="icon-box bg-light-primary rounded-circle p-4 mx-auto mb-4">
                                    <i class="bi bi-heart text-primary fs-1"></i>
                                </div>
                                <h5 class="mt-3 fw-bold">لیست علاقه‌مندی‌های شما خالی است</h5>
                                <p class="mb-4 text-muted">می‌توانید محصولات مورد علاقه خود را از فروشگاه به این لیست اضافه کنید.</p>
                                <a href="/digital-shop/index.php" class="btn btn-primary px-4">
                                    <i class="bi bi-arrow-left me-1"></i> بازگشت به فروشگاه
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        function addToCart(productId) {
            fetch('add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${encodeURIComponent(productId)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('خطا در ارتباط با سرور');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const button = document.querySelector(`button[onclick="addToCart(${productId})"]`);
                        button.innerHTML = '<i class="bi bi-check"></i>';
                        button.classList.add('btn-success');

                        setTimeout(() => {
                            button.innerHTML = '<i class="bi bi-cart-plus"></i>';
                            button.classList.remove('btn-success');
                        }, 2000);

                        Swal.fire(
                            'انجام شد!',
                            data.message || 'محصول به سبد خرید اضافه شد',
                            'success'
                        );
                    } else {
                        Swal.fire(
                            'خطا!',
                            data.message || 'خطا در افزودن محصول به سبد خرید',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire(
                        'خطا!',
                        'خطا در ارتباط با سرور: ' + error.message,
                        'error'
                    );
                });
        }
    

        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.substring(1);
            
            if (hash && ['profile', 'orders', 'favorites', 'consultations', 'messages'].includes(hash)) {
                const tabTrigger = document.querySelector(`[href="#${hash}"]`);
                if (tabTrigger) {
                    const tab = new bootstrap.Tab(tabTrigger);
                    tab.show();
                }
            }
        });