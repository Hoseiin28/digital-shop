
        document.addEventListener('DOMContentLoaded', function() {

            document.querySelectorAll('.remove-compare-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    removeFromCompare(productId);
                });
            });

            document.getElementById('clear-all-compare')?.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                clearAllCompare();
            });

            function clearAllCompare() {
                fetch('clear_compare.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=clear_all'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('خطا در پاسخ سرور');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'انجام شد',
                                text: 'تمام محصولات از لیست مقایسه حذف شدند',
                                timer: 1000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'products.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطا',
                                text: data.message || 'خطا در پاک کردن لیست مقایسه'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطا',
                            text: 'خطا در ارتباط با سرور: ' + error.message
                        });
                    });
            }

            document.querySelectorAll('.add-to-cart-btn')?.forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    addToCart(productId);
                });
            });

            const tableRows = document.querySelectorAll('.compare-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.classList.add('highlight-row');
                });
                row.addEventListener('mouseleave', function() {
                    this.classList.remove('highlight-row');
                });
            });

            const tableHeaders = document.querySelectorAll('.compare-table th');
            tableHeaders.forEach(header => {
                if (header.classList.contains('fixed-column')) return;

                const productId = header.getAttribute('data-product-id');

                header.addEventListener('mouseenter', function() {
                    document.querySelectorAll(`.compare-table td[data-product-id="${productId}"]`).forEach(cell => {
                        cell.classList.add('highlight-cell');
                    });
                });

                header.addEventListener('mouseleave', function() {
                    document.querySelectorAll(`.compare-table td[data-product-id="${productId}"]`).forEach(cell => {
                        cell.classList.remove('highlight-cell');
                    });
                });
            });
        });

        function removeFromCompare(productId) {
            fetch('remove_from_compare.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.count >= 2) {
                            window.location.reload();
                        } else {
                            window.location.href = 'products.php';
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطا',
                            text: data.message || 'خطا در حذف محصول از لیست مقایسه'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: 'خطا در ارتباط با سرور'
                    });
                });
        }

        function addToCart(productId) {
            fetch('add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&quantity=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'موفق',
                            text: 'محصول به سبد خرید اضافه شد',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        if (data.cart_count) {
                            const cartCount = document.querySelector('.cart-count');
                            if (cartCount) {
                                cartCount.textContent = data.cart_count;
                            }
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطا',
                            text: data.message || 'خطا در افزودن به سبد خرید'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: 'خطا در ارتباط با سرور'
                    });
                });
        }