        document.getElementById('image_file').addEventListener('change', function(e) {
            const preview = document.getElementById('image_preview');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const productChecks = document.querySelectorAll('.product-check');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const deleteSelected = document.getElementById('deleteSelected');
            const cancelBulk = document.getElementById('cancelBulk');
            const bulkForm = document.getElementById('bulkForm');
            
            selectAll.addEventListener('change', function() {
                productChecks.forEach(check => {
                    check.checked = this.checked;
                });
                updateSelectedCount();
            });
            
            productChecks.forEach(check => {
                check.addEventListener('change', function() {
                    updateSelectedCount();
                    selectAll.checked = [...productChecks].every(c => c.checked);
                });
            });
            
            function updateSelectedCount() {
                const selected = document.querySelectorAll('.product-check:checked').length;
                selectedCount.textContent = selected + ' محصول انتخاب شده';
                
                if (selected > 0) {
                    bulkActions.classList.add('show');
                } else {
                    bulkActions.classList.remove('show');
                }
            }
            
            deleteSelected.addEventListener('click', function() {
                const selected = document.querySelectorAll('.product-check:checked').length;
                if (selected > 0) {
                    if (confirm(`آیا از حذف ${selected} محصول انتخاب شده مطمئن هستید؟`)) {
                        bulkForm.submit();
                    }
                }
            });
            
            cancelBulk.addEventListener('click', function() {
                productChecks.forEach(check => {
                    check.checked = false;
                });
                selectAll.checked = false;
                bulkActions.classList.remove('show');
            });
        });
    