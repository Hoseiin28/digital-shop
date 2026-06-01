const colorPicker = document.getElementById('button_color');
        const colorText = document.getElementById('button_color_text');
        const colorPreview = document.getElementById('colorPreview');
        
        function updateColor() {
            const color = colorPicker.value;
            colorText.value = color;
            colorPreview.style.backgroundColor = color;
            document.documentElement.style.setProperty('--primary-color', color);
        }
        
        colorPicker.addEventListener('input', updateColor);
        colorText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9a-f]{6}$/i)) {
                colorPicker.value = this.value;
                updateColor();
            }
        });
        
        document.getElementById('addSlider').addEventListener('click', function() {
            const sliderHtml = `
                <div class="slider-item">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">تصویر اسلایدر</label>
                            <input type="file" class="form-control" name="slider_image[]" accept="image/*">
                        </div>
                        <div class="col-md-8 mb-3">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">متن روی تصویر</label>
                                    <input type="text" class="form-control" name="slider_caption[]">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">متن دکمه</label>
                                    <input type="text" class="form-control" name="slider_button_text[]">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">لینک دکمه</label>
                                    <input type="text" class="form-control" name="slider_button_link[]">
                                </div>
                            </div>
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-sm btn-danger remove-slider">
                                <i class="fas fa-trash me-1"></i> حذف اسلایدر
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('slidersContainer').insertAdjacentHTML('beforeend', sliderHtml);
        });
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-slider')) {
                if (confirm('آیا از حذف این اسلایدر مطمئن هستید؟')) {
                    e.target.closest('.slider-item').remove();
                }
            }
        });
        
        document.addEventListener('change', function(e) {
            if (e.target && e.target.matches('input[type="file"]')) {
                const fileInput = e.target;
                const previewContainer = fileInput.closest('.mb-3');
                
                if (fileInput.files && fileInput.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const oldPreview = previewContainer.querySelector('.image-preview');
                        if (oldPreview) oldPreview.remove();
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview mt-2';
                        fileInput.insertAdjacentElement('afterend', img);
                    };
                    
                    reader.readAsDataURL(fileInput.files[0]);
                }
            }
        });
        
        function updateSocialPreview() {
            const platforms = ['instagram', 'telegram', 'whatsapp', 'youtube'];
            
            platforms.forEach(platform => {
                const base = document.getElementById(`${platform}_base`).value;
                const username = document.getElementById(platform).value;
                const preview = document.getElementById(`preview-${platform}`);
                
                if (username) {
                    preview.href = base ? `${base.replace(/\/+$/, '')}/${username.replace(/^\/+/, '')}` : 
                                        `https://${platform}.com/${username.replace(/^\/+/, '')}`;
                    preview.style.pointerEvents = 'auto';
                    preview.style.opacity = '1';
                } else {
                    preview.href = '#';
                    preview.style.pointerEvents = 'none';
                    preview.style.opacity = '0.5';
                }
            });
        }
        
        ['instagram', 'telegram', 'whatsapp', 'youtube'].forEach(platform => {
            document.getElementById(platform).addEventListener('input', updateSocialPreview);
            document.getElementById(`${platform}_base`).addEventListener('input', updateSocialPreview);
        });
        
        updateSocialPreview();
        
        document.getElementById('font_family').addEventListener('change', function() {
            document.body.style.fontFamily = this.value;
        });

        