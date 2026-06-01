        document.getElementById('product_image').addEventListener('change', function(e) {
            const fileListContainer = document.getElementById('file-list-container');
            const fileCount = document.getElementById('file-count');
            
            if (this.files.length > 0) {
                fileListContainer.style.display = 'block';
                fileCount.textContent = this.files.length;
            } else {
                fileListContainer.style.display = 'none';
            }
        });