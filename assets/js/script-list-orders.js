        function loadOrderDetails(orderId) {
            const modalContent = document.getElementById('orderDetailsContent');
            modalContent.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p>در حال بارگذاری...</p></div>';
            
            fetch(`order_details.php?order_id=${orderId}`)
                .then(response => response.text())
                .then(data => {
                    modalContent.innerHTML = data;
                })
                .catch(error => {
                    modalContent.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری جزئیات سفارش</div>';
                    console.error('Error:', error);
                });
        }