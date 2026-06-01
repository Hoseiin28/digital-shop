document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.discount-checkbox');
            
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAll.checked = false;
                    } else {
                        const allChecked = Array.from(checkboxes).every(c => c.checked);
                        selectAll.checked = allChecked;
                    }
                });
            });
            
            const discountType = document.getElementById('discount_type');
            const discountValueAddon = document.getElementById('discount-addon');
            
            discountType.addEventListener('change', function() {
                discountValueAddon.textContent = this.value === 'percentage' ? '%' : 'تومان';
            });
            
            const editModals = document.querySelectorAll('.modal');
            editModals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', function() {
                    const discountId = this.id.replace('editModal', '');
                    const discountType = document.getElementById(`edit_discount_type_${discountId}`);
                    const discountValue = document.getElementById(`edit_discount_value_${discountId}`);
                    
                    if (discountType && discountValue) {
                        discountType.addEventListener('change', function() {
                            const addon = this.closest('.modal').querySelector('.discount-value-addon');
                            if (addon) {
                                addon.textContent = this.value === 'percentage' ? '%' : 'تومان';
                            }
                        });
                    }
                });
            });
        });

        document.getElementById('discount_type').addEventListener('change', function() {
            const discountType = this.value;
            const discountAddon = document.getElementById('discount-addon');
            discountAddon.textContent = discountType === 'percentage' ? '%' : 'تومان';
        });
        
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.discount-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        document.querySelectorAll('select[name="product_id"]').forEach(select => {
            select.addEventListener('focus', function() {
                this.size = 5;
            });
            
            select.addEventListener('blur', function() {
                this.size = 1;
            });
        });