document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.review-checkbox');
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
            
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
                updateBulkDeleteBtn();
            });
            
            checkboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        selectAll.checked = [...checkboxes].every(c => c.checked);
        updateBulkDeleteBtn();
        console.log(`Checkbox ${checkbox.value} checked: ${checkbox.checked}`);
    });
});
            
            function updateBulkDeleteBtn() {
                const anyChecked = [...checkboxes].some(c => c.checked);
                bulkDeleteBtn.disabled = !anyChecked;
            }
            
            window.toggleReplyForm = function(reviewId, event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                const form = document.getElementById(`reply-form-${reviewId}`);
                form.style.display = form.style.display === 'none' || !form.style.display ? 'block' : 'none';
                
                if (form.style.display === 'block') {
                    form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                
                return false;
            }

            function submitReplyForm(reviewId) {
                const form = document.querySelector(`#reply-form-${reviewId} form`);
                const formData = new FormData(form);
                
                fetch('list-reviews.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('خطا در ارسال پاسخ. لطفاً دوباره تلاش کنید.');
                });
                
                return false;
            }
            
            window.setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });