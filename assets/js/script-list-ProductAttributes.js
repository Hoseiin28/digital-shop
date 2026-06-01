        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.attribute-checkbox');
            const bulkActions = document.getElementById('bulkActions');
            const cancelBulk = document.getElementById('cancelBulk');
            
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
                toggleBulkActions();
            });
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = [...checkboxes].every(cb => cb.checked);
                    selectAll.checked = allChecked;
                    toggleBulkActions();
                });
            });
            
            function toggleBulkActions() {
                const anyChecked = [...checkboxes].some(cb => cb.checked);
                if (anyChecked) {
                    bulkActions.classList.add('show');
                } else {
                    bulkActions.classList.remove('show');
                }
            }
            
            cancelBulk.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                selectAll.checked = false;
                bulkActions.classList.remove('show');
            });
        });
    

        document.addEventListener('DOMContentLoaded', function() {
    const selectElements = document.querySelectorAll('select.form-select');
    
    selectElements.forEach(select => {
        // تنظیم ارتفاع بر اساس تعداد آیتم‌ها (حداکثر 200px)
        const itemCount = select.options.length;
        const visibleItems = Math.min(itemCount, 5); // نمایش 5 آیتم به صورت پیش‌فرض
        const itemHeight = 35; // ارتفاع تقریبی هر آیتم
        
        // محاسبه ارتفاع کل
        const totalHeight = visibleItems * itemHeight;
        select.style.height = `${Math.min(totalHeight, 200)}px`;
        
        // اضافه کردن قابلیت جستجو برای لیست‌های طولانی
        if (itemCount > 10) {
            const wrapper = document.createElement('div');
            wrapper.className = 'select-search-wrapper';
            wrapper.style.position = 'relative';
            
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'جستجو...';
            searchInput.className = 'form-control mb-2';
            searchInput.style.width = '100%';
            
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                Array.from(select.options).forEach(option => {
                    const text = option.text.toLowerCase();
                    option.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
            
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(searchInput);
            wrapper.appendChild(select);
        }
    });
});


document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const imagePreview = document.getElementById('product-image-preview');
    
    productSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const imageUrl = selectedOption.getAttribute('data-image');
        
        if (imageUrl) {
            imagePreview.innerHTML = `<img src="${imageUrl}" alt="تصویر محصول" class="img-thumbnail" style="max-height: 100px;">`;
        } else {
            imagePreview.innerHTML = '';
        }
    });
});

$(document).ready(function() {
    function formatProduct(product) {
        if (!product.id) {
            return product.text;
        }
        
        var $product = $(
            '<div class="d-flex align-items-center">' +
                '<img src="' + $(product.element).data('image') + '" class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">' +
                '<span>' + product.text + '</span>' +
            '</div>'
        );
        return $product;
    }

    $('#product_id').select2({
        templateResult: formatProduct,
        templateSelection: formatProduct,
        placeholder: "انتخاب محصول",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#product_id').parent()
    });
    
    $('#product_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const imageUrl = selectedOption.data('image');
        
        if (imageUrl) {
            $('#product-image-preview').html(
                `<img src="${imageUrl}" alt="تصویر محصول" class="img-thumbnail" style="max-height: 100px;">`
            );
        } else {
            $('#product-image-preview').html('');
        }
    });
});