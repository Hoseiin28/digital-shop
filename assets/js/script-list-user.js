$('#userDetailsModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const userId = button.data('user-id');
            const modal = $(this);

            $.ajax({
                url: 'get_user_details.php?id=' + userId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        modal.find('.modal-body').html('<div class="alert alert-danger">' + data.error + '</div>');
                        return;
                    }
                    
                    const detailsHtml = `
                        <div class="text-center mb-3">
                            <img src="${data.avatar || 'static/img/avatars/default-avatar.jpg'}" 
                                 class="avatar-img" style="width: 100px; height: 100px;">
                        </div>
                        <div class="user-details">
                            <p><strong><i class="fas fa-user me-2"></i>نام:</strong> ${data.name}</p>
                            <p><strong><i class="fas fa-envelope me-2"></i>ایمیل:</strong> ${data.email}</p>
                            <p><strong><i class="fas fa-phone me-2"></i>تلفن:</strong> ${data.phone || 'ثبت نشده'}</p>
                            <p><strong><i class="fas fa-user-tag me-2"></i>نقش:</strong> 
                                <span class="badge rounded-pill ${data.role === 'admin' ? 'badge-admin' : 'badge-user'}">
                                    ${data.role === 'admin' ? 'مدیر' : 'کاربر'}
                                </span>
                            </p>
                            <p><strong><i class="fas fa-calendar-alt me-2"></i>تاریخ عضویت:</strong> 
                                ${new Date(data.created_at).toLocaleDateString('fa-IR')}
                            </p>
                            ${data.address ? `<p><strong><i class="fas fa-map-marker-alt me-2"></i>آدرس:</strong> ${data.address}</p>` : ''}
                        </div>
                    `;
                    
                    modal.find('.modal-body').html(detailsHtml);
                },
                error: function() {
                    modal.find('.modal-body').html('<div class="alert alert-danger">خطا در دریافت اطلاعات کاربر</div>');
                }
            });
        });

        setTimeout(() => {
            $('.alert').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);