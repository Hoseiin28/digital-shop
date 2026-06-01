
        document.getElementById('avatar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('avatarPreview').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        document.getElementById('phone').addEventListener('input', function() {
            const phoneRegex = /^\+?[0-9]{7,15}$/;
            if (this.value && !phoneRegex.test(this.value)) {
                this.setCustomValidity('شماره تلفن معتبر نیست');
            } else {
                this.setCustomValidity('');
            }
        });