     document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.faq-checkbox');
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                    updateBulkDeleteBtn();
                });
            }
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (selectAll) {
                        selectAll.checked = [...checkboxes].every(c => c.checked);
                    }
                    updateBulkDeleteBtn();
                });
            });
            
            function updateBulkDeleteBtn() {
                if (bulkDeleteBtn) {
                    const anyChecked = [...checkboxes].some(c => c.checked);
                    bulkDeleteBtn.disabled = !anyChecked;
                }
            }
            
            const bulkForm = document.getElementById('bulkForm');
            if (bulkForm) {
                bulkForm.addEventListener('submit', function(e) {
                    const selectedCount = document.querySelectorAll('.faq-checkbox:checked').length;
                    if (selectedCount > 0 && !confirm(`آیا از حذف ${selectedCount} سوال انتخاب شده مطمئن هستید؟`)) {
                        e.preventDefault();
                    }
                });
            }
            
            const toggleAddForm = document.getElementById('toggleAddForm');
            const toggleAddFormEmpty = document.getElementById('toggleAddFormEmpty');
            const addForm = document.getElementById('addForm');
            const cancelAdd = document.getElementById('cancelAdd');
            
            if (toggleAddForm && addForm) {
                toggleAddForm.addEventListener('click', function() {
                    addForm.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            
            if (toggleAddFormEmpty && addForm) {
                toggleAddFormEmpty.addEventListener('click', function() {
                    addForm.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            
            if (cancelAdd && addForm) {
                cancelAdd.addEventListener('click', function() {
                    addForm.style.display = 'none';
                });
            }
        });
    