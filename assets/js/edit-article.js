tinymce.init({
    selector: '#content',
    height: 500,
    plugins: 'lists link image table code',
    toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
    language: 'fa',
    forced_root_block: false,
    forced_root_block_attrs: false,
    convert_newlines_to_brs: true,
    setup: function(editor) {
      editor.on('change', function() {
        editor.save();
      });
    }
  });

const uploadZone = document.getElementById('uploadZone');
const imageInput = document.getElementById('imageInput');
const newPreview = document.getElementById('newPreview');
const uploadText = document.getElementById('uploadText');

uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('drop-zone-active');
});

['dragleave', 'drop'].forEach(event => {
    uploadZone.addEventListener(event, () => {
        uploadZone.classList.remove('drop-zone-active');
    });
});

uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    handleImage(file);
});

imageInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) handleImage(file);
});

function handleImage(file) {
    const reader = new FileReader();
    
    reader.onload = (e) => {
        newPreview.querySelector('img').src = e.target.result;
        newPreview.style.display = 'block';
        uploadText.style.display = 'none';
    };
    
    reader.onerror = () => {
        alert('خطا در خواندن فایل!');
    };
    
    if (file) {
        if (!file.type.startsWith('image/')) {
            alert('فقط فایل‌های تصویری مجاز هستند!');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            alert('حجم فایل باید کمتر از ۵ مگابایت باشد!');
            return;
        }
        
        reader.readAsDataURL(file);
    }
}

function validateForm() {
    const title = document.querySelector('[name="title"]').value.trim();
    const content = tinymce.get('content').getContent().trim();

    if (title.length < 10) {
        alert('عنوان باید حداقل ۱۰ کاراکتر داشته باشد!');
        return false;
    }

    if (content.length < 100) {
        alert('محتوای مقاله باید حداقل ۱۰۰ کاراکتر داشته باشد!');
        return false;
    }

    return true;
}