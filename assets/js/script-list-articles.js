            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('selectAll');
                const articleChecks = document.querySelectorAll('.article-check');
                const bulkActions = document.getElementById('bulkActions');
                const selectedCount = document.getElementById('selectedCount');
                const deleteSelected = document.getElementById('deleteSelected');
                const cancelBulk = document.getElementById('cancelBulk');
                const bulkForm = document.getElementById('bulkForm');
                
                selectAll.addEventListener('change', function() {
                    articleChecks.forEach(check => {
                        check.checked = this.checked;
                    });
                    updateSelectedCount();
                });
                
                articleChecks.forEach(check => {
                    check.addEventListener('change', function() {
                        updateSelectedCount();
                        selectAll.checked = [...articleChecks].every(c => c.checked);
                    });
                });
                
                function updateSelectedCount() {
                    const selected = document.querySelectorAll('.article-check:checked').length;
                    selectedCount.textContent = selected + ' مقاله انتخاب شده';
                    
                    if (selected > 0) {
                        bulkActions.classList.add('show');
                    } else {
                        bulkActions.classList.remove('show');
                    }
                }
                
                deleteSelected.addEventListener('click', function() {
                    const selected = document.querySelectorAll('.article-check:checked').length;
                    if (selected > 0) {
                        if (confirm(`آیا از حذف ${selected} مقاله انتخاب شده مطمئن هستید؟`)) {
                            bulkForm.submit();
                        }
                    }
                });
                
                cancelBulk.addEventListener('click', function() {
                    articleChecks.forEach(check => {
                        check.checked = false;
                    });
                    selectAll.checked = false;
                    bulkActions.classList.remove('show');
                });
                
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        