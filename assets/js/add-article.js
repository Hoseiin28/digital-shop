tinymce.init({
    selector: '#content',
    height: 400,
    plugins: 'lists link image table',
    toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image',
    language: 'fa',
    images_upload_url: 'upload.php',
    automatic_uploads: true,
    forced_root_block: false,
    forced_root_block_attrs: false,
    remove_linebreaks: false,
    convert_newlines_to_brs: true,
    setup: function(editor) {
        editor.on('change', function() {
            editor.save();
        });
    }
});

        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('image');
        const preview = document.getElementById('imagePreview');

        dropArea.addEventListener('click', () => fileInput.click());

        const handleDrag = (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (e.type === 'dragenter' || e.type === 'dragover') {
                dropArea.classList.add('drag-over');
            } else {
                dropArea.classList.remove('drag-over');
            }
        };

        const handleDrop = (e) => {
            e.preventDefault();
            dropArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updatePreview(files[0]);
            }
        };

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(event => {
            dropArea.addEventListener(event, handleDrag);
        });

        dropArea.addEventListener('drop', handleDrop);

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updatePreview(e.target.files[0]);
            }
        });

        function updatePreview(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        document.getElementById('articleForm').addEventListener('submit', (e) => {
            const title = document.querySelector('[name="title"]').value.trim();
            const content = tinymce.get('content').getContent().trim();
            const image = fileInput.files[0];
            let errors = [];

            if (title.length < 10) errors.push('عنوان باید حداقل ۱۰ کاراکتر داشته باشد');
            if (content.length < 100) errors.push('محتوا باید حداقل ۱۰۰ کاراکتر داشته باشد');
            if (!image) errors.push('لطفا تصویری انتخاب کنید');

            if (errors.length > 0) {
                e.preventDefault();
                alert('خطاها:\n' + errors.join('\n'));
            }
        });