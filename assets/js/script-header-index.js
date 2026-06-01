
        function refreshContent() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');

                    const newContent = doc.getElementById('refresh').innerHTML;

                    document.getElementById('refresh').innerHTML = newContent;
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        setInterval(refreshContent, 1000);

        $('.mobile-menu-toggle').click(function() {
            $('.mobile-nav').addClass('active');
            $('.mobile-nav-overlay').addClass('active');
            $('body').css('overflow', 'hidden');
        });

        $('.mobile-nav-close, .mobile-nav-overlay').click(function() {
            $('.mobile-nav').removeClass('active');
            $('.mobile-nav-overlay').removeClass('active');
            $('body').css('overflow', 'auto');
            $('.mobile-nav-dropdown').removeClass('open');
        });

        $('.mobile-dropdown-toggle').click(function(e) {
            e.preventDefault();
            $(this).parent().toggleClass('open');
            $(this).parent().siblings('.mobile-nav-dropdown').removeClass('open');
        });

        $('.mobile-nav-dropdown > a').click(function(e) {
            if ($(this).parent().hasClass('mobile-nav-dropdown')) {
                if ($(this).siblings('.mobile-sub-nav').length > 0) {
                    e.preventDefault();
                    $(this).parent().toggleClass('open');
                    $(this).find('.dropdown-icon').toggleClass('fa-chevron-up fa-chevron-down');
                }
            }
        });

        $('.user-btn').click(function(e) {
            e.stopPropagation();
            $(this).siblings('.dropdown-menu').toggleClass('show');
        });

        $(document).click(function() {
            $('.dropdown-menu').removeClass('show');
        });

        $('.mega-menu').hover(function() {
            $(this).find('.mega-menu-content').stop(true, true).slideDown(200);
        }, function() {
            $(this).find('.mega-menu-content').stop(true, true).slideUp(200);
        });

        $('.search-form input').on('input', function() {
            const query = $(this).val().trim();

            if (query.length > 2) {
                $.get('/digital-shop/public/search-suggestions.php', {
                    query: query
                }, function(data) {
                    if (data.length > 0) {
                        let suggestionsHtml = '';
                        data.forEach(item => {
                            suggestionsHtml += `
                                    <a href="/digital-shop/public/products.php?id=${item.id}">
                                        <img src="${item.image_url || '/digital-shop/image/no-image.jpg'}" alt="${item.name}">
                                        <span>${item.name}</span>
                                    </a>
                                `;
                        });
                        $('.search-suggestions').html(suggestionsHtml).slideDown(200);
                    } else {
                        $('.search-suggestions').slideUp(200);
                    }
                }).fail(function() {
                    $('.search-suggestions').slideUp(200);
                });
            } else {
                $('.search-suggestions').slideUp(200);
            }
        });

        $(document).click(function(e) {
            if (!$(e.target).closest('.search-form').length) {
                $('.search-suggestions').slideUp(200);
            }
        });

        function updateCounts() {
            $.ajax({
                url: '/digital-shop/public/get_counts.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.cartCount > 0) {
                        $('.cart-count, .mobile-cart-count').text(data.cartCount).show();
                    } else {
                        $('.cart-count, .mobile-cart-count').text('').hide();
                    }

                    if (data.compareCount > 0) {
                        $('.compare-count, .mobile-compare-count').text(data.compareCount).show();
                    } else {
                        $('.compare-count, .mobile-compare-count').text('').hide();
                    }
                    console.log('Compare count updated:', data.compareCount);
                },
                error: function() {
                    console.error('Error fetching counts');
                },
                complete: function() {
                    setTimeout(updateCounts, 100);
                }
            });
        }

        updateCounts();

        function addToCart(productId, quantity) {
            $.post('/digital-shop/public/add_to_cart.php', {
                product_id: productId,
                quantity: quantity
            }, function(response) {
                if (response.success) {
                    updateCounts();
                    showSuccessMessage('محصول به سبد خرید اضافه شد');
                } else {
                    showErrorMessage(response.message);
                }
            }, 'json');
        }

        function removeFromCart(itemId) {
            $.post('/digital-shop/public/remove_from_cart.php', {
                item_id: itemId
            }, function(response) {
                if (response.success) {
                    updateCounts();
                    showSuccessMessage('محصول از سبد خرید حذف شد');
                } else {
                    showErrorMessage(response.message);
                }
            }, 'json');
        }

        function addToCompare(productId) {
            $.ajax({
                url: '/digital-shop/public/add_to_compare.php',
                method: 'POST',
                data: {
                    product_id: productId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.compareCount > 0) {
                            $('.compare-count, .mobile-compare-count').text(response.compareCount).show();
                        } else {
                            $('.compare-count, .mobile-compare-count').text('').hide();
                        }
                        alert(response.message);
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                }
            });
        }

        $('.cart-btn').hover(function() {
            $(this).find('i').css('animation', 'shake 0.5s ease-in-out');
        }, function() {
            $(this).find('i').css('animation', '');
        });

        $('.compare-btn').hover(function() {
            $(this).find('i').css('transform', 'rotate(90deg)');
        }, function() {
            $(this).find('i').css('transform', '');
        });





        $(document).ready(function() {
            $('.cart-btn').click(function(e) {
                e.preventDefault();
                openCartModal();
            });

            $('.close-cart-modal, .cart-modal-overlay').click(function() {
                closeCartModal();
            });

            function openCartModal() {
                $('.cart-modal-overlay').fadeIn(200);
                $('.cart-modal').css('right', '0');
                loadCartItems();
            }

            function closeCartModal() {
                $('.cart-modal').css('right', '-400px');
                $('.cart-modal-overlay').fadeOut(200);
            }

            function loadCartItems() {
                $.ajax({
                    url: '/digital-shop/public/get_cart_items.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            updateCartModal(response.items, response.totals);
                        } else {
                            showEmptyCart();
                        }
                    },
                    error: function() {
                        showEmptyCart();
                    }
                });
            }

            function updateCartModal(items, totals) {
                const $container = $('.cart-items-container');

                if (items.length === 0) {
                    showEmptyCart();
                    return;
                }

                let html = '';

                items.forEach(item => {
                    const originalPrice = item.discount_price > 0 ?
                        `<span class="original-price">${item.price.toLocaleString()} تومان</span>` : '';

                    const finalPrice = item.discount_price > 0 ?
                        item.discount_price : item.price;

                    html += `
                    <div class="cart-item" data-item-id="${item.id}">
                        <img src="/digital-shop/public/${item.image_url || 'image/default-product.jpg'}" 
                             alt="${item.name}" class="cart-item-image">
                        <div class="cart-item-details">
                            <h4 class="cart-item-title">${item.name}</h4>
                            <div class="cart-item-price">
                                ${originalPrice}
                                <span class="final-price">${finalPrice.toLocaleString()} تومان</span>
                            </div>
                            <div class="cart-item-actions">
                                <button class="remove-item-btn" data-item-id="${item.id}">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                                <div class="quantity-control">
                                    <button class="quantity-btn decrease-btn" data-item-id="${item.id}">-</button>
                                    <input type="text" class="quantity-input" value="${item.quantity}" 
                                           data-item-id="${item.id}" readonly>
                                    <button class="quantity-btn increase-btn" data-item-id="${item.id}">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                });

                $container.html(html);

                $('.cart-total-price').text(totals.total.toLocaleString() + ' تومان');
                $('.cart-discount').text(totals.discount.toLocaleString() + ' تومان');
                $('.cart-final-price').text(totals.final_price.toLocaleString() + ' تومان');

                $('.checkout-btn').prop('disabled', false);

                bindCartEvents();
            }

            function showEmptyCart() {
                $('.cart-items-container').html(`
                <div class="empty-cart-message">
                    <i class="fas fa-shopping-cart"></i>
                    <p>سبد خرید شما خالی است</p>
                </div>
            `);

                $('.cart-total-price').text('۰ تومان');
                $('.cart-discount').text('۰ تومان');
                $('.cart-final-price').text('۰ تومان');
                $('.checkout-btn').prop('disabled', true);
            }

            function bindCartEvents() {
                $('.remove-item-btn').click(function() {
                    const itemId = $(this).data('item-id');
                    removeCartItem(itemId);
                });

                $('.decrease-btn').click(function() {
                    const itemId = $(this).data('item-id');
                    updateCartItemQuantity(itemId, -1);
                });

                $('.increase-btn').click(function() {
                    const itemId = $(this).data('item-id');
                    updateCartItemQuantity(itemId, 1);
                });

                $('.checkout-btn').click(function() {
                    window.location.href = '/digital-shop/public/checkout.php';
                });
            }

            function removeCartItem(itemId) {
                $.ajax({
                    url: '/digital-shop/public/remove_from_cart.php',
                    method: 'POST',
                    data: {
                        item_id: itemId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadCartItems();
                            updateCounts();
                            showSuccessMessage('محصول از سبد خرید حذف شد');
                        } else {
                            showErrorMessage(response.message);
                        }
                    },
                    error: function() {
                        showErrorMessage('خطا در ارتباط با سرور');
                    }
                });
            }

            function updateCartItemQuantity(itemId, change) {
                $.ajax({
                    url: '/digital-shop/public/update_cart_item.php',
                    method: 'POST',
                    data: {
                        item_id: itemId,
                        change: change
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadCartItems();
                            updateCounts();
                        } else {
                            showErrorMessage(response.message);
                        }
                    }
                });
            }

            function showSuccessMessage(message) {
                const $msg = $(`
                <div class="success-message animate__animated animate__fadeInUp">
                    <i class="fas fa-check-circle"></i>
                    <span>${message}</span>
                </div>
            `);

                $('body').append($msg);

                setTimeout(() => {
                    $msg.addClass('animate__fadeOutDown');
                    setTimeout(() => $msg.remove(), 500);
                }, 3000);
            }

            function showErrorMessage(message) {
                const $msg = $(`
                <div class="error-message animate__animated animate__fadeInUp">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                </div>
            `);

                $('body').append($msg);

                setTimeout(() => {
                    $msg.addClass('animate__fadeOutDown');
                    setTimeout(() => $msg.remove(), 500);
                }, 3000);
            }

            const style = document.createElement('style');
            style.textContent = `
            .success-message, .error-message {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 4px;
                color: white;
                display: flex;
                align-items: center;
                z-index: 10000;
                box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            }
            
            .success-message {
                background-color: #27ae60;
            }
            
            .error-message {
                background-color: #e74c3c;
            }
            
            .success-message i, .error-message i {
                margin-left: 10px;
                font-size: 20px;
            }
        `;
            document.head.appendChild(style);
        });

        
document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.createElement('button');
    backToTopButton.className = 'back-to-top';
    backToTopButton.innerHTML = '<i class="fas fa-arrow-up"></i>';
    document.body.appendChild(backToTopButton);

    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('active');
        } else {
            backToTopButton.classList.remove('active');
        }
    });

    backToTopButton.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});
    

$(document).ready(function() {
    const header = $('.main-header');
    const headerHeight = header.outerHeight();
    const welcomeMessageHeight = $('.welcome-message').outerHeight() || 0;
    const topBarHeight = $('.top-bar').outerHeight() || 0;
    const totalOffset = headerHeight + welcomeMessageHeight + topBarHeight;

    $(window).scroll(function() {
        if ($(this).scrollTop() > totalOffset) {
            header.addClass('sticky');
            $('body').addClass('sticky-header');
        } else {
            header.removeClass('sticky');
            $('body').removeClass('sticky-header');
        }
    });

    $(window).resize(function() {
        const newHeaderHeight = header.outerHeight();
        const newWelcomeMessageHeight = $('.welcome-message').outerHeight() || 0;
        const newTopBarHeight = $('.top-bar').outerHeight() || 0;
        totalOffset = newHeaderHeight + newWelcomeMessageHeight + newTopBarHeight;
    });
});
    