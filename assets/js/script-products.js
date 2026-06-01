function showLoginMessage() {
            Swal.fire({
                title: 'ورود به سیستم',
                text: 'برای استفاده از این ویژگی باید وارد حساب کاربری خود شوید',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'ورود',
                cancelButtonText: 'بعداً',
                confirmButtonColor: '#4a6bff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
        }

        

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    addToCart(productId);
                });
            });
            
            document.querySelectorAll('.favorite-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    toggleFavorite(this, productId);
                });
            });
            
            document.querySelectorAll('.quick-view-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    window.location.href = `product-details.php?id=${productId}`;
                });
            });
        });


document.querySelectorAll('.compare-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const productId = this.dataset.productId;
        
        if (this.checked) {
            addToCompare(productId);
        } else {
            removeFromCompare(productId);
        }
    });
});

function addToCompare(productId) {
    fetch('add_to_compare.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            
            if (data.compareCount !== undefined) {
                const compareCountElements = document.querySelectorAll('.compare-count, .mobile-compare-count');
                compareCountElements.forEach(el => {
                    if (data.compareCount > 0) {
                        el.textContent = data.compareCount;
                        el.style.display = 'inline-block';
                    } else {
                        el.style.display = 'none';
                    }
                });
            }
        } else {
            document.querySelector(`.compare-checkbox[data-product-id="${productId}"]`).checked = false;
            showMessage(data.message || 'خطا در افزودن به لیست مقایسه', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.querySelector(`.compare-checkbox[data-product-id="${productId}"]`).checked = false;
        showMessage('خطا در ارتباط با سرور', 'error');
    });
}

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
            showMessage(data.message, 'success');
            
            if (data.compareCount !== undefined) {
                const compareCountElements = document.querySelectorAll('.compare-count, .mobile-compare-count');
                compareCountElements.forEach(el => {
                    if (data.compareCount > 0) {
                        el.textContent = data.compareCount;
                        el.style.display = 'inline-block';
                    } else {
                        el.style.display = 'none';
                    }
                });
            }
        } else {
            document.querySelector(`.compare-checkbox[data-product-id="${productId}"]`).checked = true;
            showMessage(data.message || 'خطا در حذف از لیست مقایسه', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.querySelector(`.compare-checkbox[data-product-id="${productId}"]`).checked = true;
        showMessage('خطا در ارتباط با سرور', 'error');
    });
}

function updateCompareButton() {
    const compareButton = document.getElementById('compare-button');
    const checkedBoxes = document.querySelectorAll('.compare-checkbox:checked');
    
    if (checkedBoxes.length >= 2) {
        compareButton.style.display = 'inline-block';
        
        const productIds = Array.from(checkedBoxes).map(checkbox => checkbox.dataset.productId);
        
        compareButton.href = `compare.php?products=${productIds.join(',')}`;
    } else {
        compareButton.style.display = 'none';
    }
}

document.querySelectorAll('.compare-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateCompareButton);
});

updateCompareButton();



