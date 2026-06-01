<?php
session_start();
require_once 'config.php';

$about_us = [];
$team_members = [];

$sql = "SELECT * FROM AboutUs LIMIT 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $about_us = $result->fetch_assoc();

    $about_us_id = $about_us['id'];
    $sql_team = "SELECT * FROM TeamMembers WHERE about_us_id = $about_us_id ORDER BY id ASC";
    $result_team = $conn->query($sql_team);
    if ($result_team->num_rows > 0) {
        while ($row = $result_team->fetch_assoc()) {
            $team_members[] = $row;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_about':
                $description = $conn->real_escape_string($_POST['description']);
                $established_date = $conn->real_escape_string($_POST['established_date']);
                $mission = $conn->real_escape_string($_POST['mission']);
                $vision = $conn->real_escape_string($_POST['vision']);

                if (!empty($about_us)) {
                    $sql = "UPDATE AboutUs SET 
                        description = '$description', 
                        established_date = '$established_date', 
                        mission = '$mission', 
                        vision = '$vision',
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = " . $about_us['id'];

                    if ($conn->query($sql)) {
                        $_SESSION['success_message'] = 'اطلاعات درباره ما با موفقیت به روزرسانی شد.';
                    } else {
                        $_SESSION['error_message'] = 'خطا در به روزرسانی اطلاعات: ' . $conn->error;
                    }
                } else {
                    $sql = "INSERT INTO AboutUs 
                        (description, established_date, mission, vision) 
                        VALUES ('$description', '$established_date', '$mission', '$vision')";

                    if ($conn->query($sql)) {
                        $_SESSION['success_message'] = 'اطلاعات درباره ما با موفقیت ایجاد شد.';
                        $about_us_id = $conn->insert_id;
                    } else {
                        $_SESSION['error_message'] = 'خطا در ایجاد اطلاعات: ' . $conn->error;
                    }
                }

                header('Location: manage-about-us.php');
                exit();
                break;

            case 'add_team_member':
                if (empty($about_us)) {
                    $_SESSION['error_message'] = 'لطفا ابتدا اطلاعات اصلی درباره ما را ثبت کنید.';
                    header('Location: manage-about-us.php');
                    exit();
                }

                $name = $conn->real_escape_string($_POST['name']);
                $position = $conn->real_escape_string($_POST['position']);
                $bio = $conn->real_escape_string($_POST['bio']);
                $contact_email = $conn->real_escape_string($_POST['contact_email'] ?? '');
                $contact_phone = $conn->real_escape_string($_POST['contact_phone'] ?? '');
                $instagram_link = $conn->real_escape_string($_POST['member_instagram'] ?? '');
                $linkedin_link = $conn->real_escape_string($_POST['member_linkedin'] ?? '');
                $telegram_link = $conn->real_escape_string($_POST['member_telegram'] ?? '');

                $avatar = '';
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../image/team/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $file_name = 'member_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                    $file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $file_path)) {
                        $avatar = $file_path;
                    }
                }

                $sql = "INSERT INTO TeamMembers 
                    (about_us_id, name, position, bio, avatar, contact_email, contact_phone, instagram_link, linkedin_link, telegram_link) 
                    VALUES (" . $about_us['id'] . ", '$name', '$position', '$bio', '$avatar', '$contact_email', '$contact_phone', '$instagram_link', '$linkedin_link', '$telegram_link')";

                if ($conn->query($sql)) {
                    $_SESSION['success_message'] = 'عضو جدید با موفقیت به تیم اضافه شد.';
                } else {
                    $_SESSION['error_message'] = 'خطا در افزودن عضو جدید: ' . $conn->error;
                }

                header('Location: manage-about-us.php');
                exit();
                break;

            case 'update_team_member':
                $member_id = intval($_POST['member_id']);
                $name = $conn->real_escape_string($_POST['name']);
                $position = $conn->real_escape_string($_POST['position']);
                $bio = $conn->real_escape_string($_POST['bio']);
                $contact_email = $conn->real_escape_string($_POST['contact_email'] ?? '');
                $contact_phone = $conn->real_escape_string($_POST['contact_phone'] ?? '');
                $instagram_link = $conn->real_escape_string($_POST['member_instagram'] ?? '');
                $linkedin_link = $conn->real_escape_string($_POST['member_linkedin'] ?? '');
                $telegram_link = $conn->real_escape_string($_POST['member_telegram'] ?? '');

                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../image/team/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $file_name = 'member_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                    $file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $file_path)) {
                        $sql = "SELECT avatar FROM TeamMembers WHERE id = $member_id";
                        $result = $conn->query($sql);
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $old_avatar = $row['avatar'];
                            if ($old_avatar && file_exists($old_avatar)) {
                                unlink($old_avatar);
                            }
                        }

                        $avatar = $file_path;

                        $sql = "UPDATE TeamMembers SET 
                            name = '$name', 
                            position = '$position', 
                            bio = '$bio', 
                            avatar = '$avatar', 
                            contact_email = '$contact_email',
                            contact_phone = '$contact_phone',
                            instagram_link = '$instagram_link',
                            linkedin_link = '$linkedin_link',
                            telegram_link = '$telegram_link',
                            updated_at = CURRENT_TIMESTAMP 
                            WHERE id = $member_id";
                    }
                } else {
                    $sql = "UPDATE TeamMembers SET 
                        name = '$name', 
                        position = '$position', 
                        bio = '$bio', 
                        contact_email = '$contact_email',
                        contact_phone = '$contact_phone',
                        instagram_link = '$instagram_link',
                        linkedin_link = '$linkedin_link',
                        telegram_link = '$telegram_link',
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = $member_id";
                }

                if ($conn->query($sql)) {
                    $_SESSION['success_message'] = 'اطلاعات عضو تیم با موفقیت به روزرسانی شد.';
                } else {
                    $_SESSION['error_message'] = 'خطا در به روزرسانی عضو تیم: ' . $conn->error;
                }

                header('Location: manage-about-us.php');
                exit();
                break;

            case 'delete_team_member':
                $member_id = intval($_POST['member_id']);

                $sql = "SELECT avatar FROM TeamMembers WHERE id = $member_id";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $avatar = $row['avatar'];
                    if ($avatar && file_exists($avatar)) {
                        unlink($avatar);
                    }
                }

                $sql = "DELETE FROM TeamMembers WHERE id = $member_id";
                if ($conn->query($sql)) {
                    $_SESSION['success_message'] = 'عضو تیم با موفقیت حذف شد.';
                } else {
                    $_SESSION['error_message'] = 'خطا در حذف عضو تیم: ' . $conn->error;
                }

                header('Location: manage-about-us.php');
                exit();
                break;
        }
    }
}

$settings = [];
$sql_settings = "SELECT * FROM ShopSettings LIMIT 1";
$result_settings = $conn->query($sql_settings);
if ($result_settings->num_rows > 0) {
    $settings = $result_settings->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت درباره ما</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-manage-about-us.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= isset($settings['button_color']) ? $settings['button_color'] : '#4e73df' ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }

        body {
            font-family: <?= isset($settings['font_family']) ? $settings['font_family'] : 'IRANSans, sans-serif' ?>;
            background-color: #f8f9fc;
            color: #333;
        }
    </style>
</head>

<body>
    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-info-circle me-2"></i>
                    مدیریت درباره ما
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars(isset($settings['shop_name']) ? $settings['shop_name'] : 'فروشگاه من') ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <div class="container mb-5">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">محتوا و اطلاعات اصلی</h5>
            </div>
            <div class="card-body">
                <form method="post" action="manage-about-us.php">
                    <input type="hidden" name="action" value="update_about">

                    <div class="mb-3">
                        <label for="description" class="form-label">توضیحات درباره ما</label>
                        <div class="editor-toolbar">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')"><i class="fas fa-bold"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')"><i class="fas fa-italic"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')"><i class="fas fa-underline"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertUnorderedList')"><i class="fas fa-list-ul"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertOrderedList')"><i class="fas fa-list-ol"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('createLink')"><i class="fas fa-link"></i></button>
                        </div>
                        <div id="description" class="editor-content" contenteditable="true"><?= isset($about_us['description']) ? htmlspecialchars_decode($about_us['description']) : '' ?></div>
                        <textarea name="description" class="d-none" id="description-textarea"><?= isset($about_us['description']) ? htmlspecialchars($about_us['description']) : '' ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="established_date" class="form-label">تاریخ تأسیس</label>
                            <input type="date" class="form-control" id="established_date" name="established_date"
                                value="<?= isset($about_us['established_date']) ? htmlspecialchars($about_us['established_date']) : '' ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="mission" class="form-label">ماموریت ما</label>
                        <textarea class="form-control" id="mission" name="mission" rows="3"><?= isset($about_us['mission']) ? htmlspecialchars($about_us['mission']) : '' ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="vision" class="form-label">چشم انداز ما</label>
                        <textarea class="form-control" id="vision" name="vision" rows="3"><?= isset($about_us['vision']) ? htmlspecialchars($about_us['vision']) : '' ?></textarea>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>ذخیره تغییرات
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">اعضای تیم</h5>
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="fas fa-plus me-1"></i>افزودن عضو جدید
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($team_members)): ?>
                    <div class="alert alert-info">هنوز هیچ عضوی به تیم اضافه نشده است.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th width="80">تصویر</th>
                                    <th>نام</th>
                                    <th>سمت</th>
                                    <th>توضیحات</th>
                                    <th width="150">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($team_members as $member): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($member['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($member['avatar']) ?>" alt="<?= htmlspecialchars($member['name']) ?>" class="team-member-avatar">
                                            <?php else: ?>
                                                <div class="team-member-avatar bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-user text-white" style="font-size: 2rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($member['name']) ?></td>
                                        <td><?= htmlspecialchars($member['position']) ?></td>
                                        <td><?= !empty($member['bio']) ? nl2br(htmlspecialchars(substr($member['bio'], 0, 100) . (strlen($member['bio']) > 100 ? '...' : ''))) : '---' ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary"
                                                onclick="editMember(
                                                        <?= $member['id'] ?>, 
                                                        '<?= htmlspecialchars($member['name'], ENT_QUOTES) ?>', 
                                                        '<?= htmlspecialchars($member['position'], ENT_QUOTES) ?>', 
                                                        `<?= htmlspecialchars($member['bio'], ENT_QUOTES) ?>`,
                                                        '<?= $member['contact_email'] ?? '' ?>',
                                                        '<?= $member['contact_phone'] ?? '' ?>',
                                                        '<?= $member['instagram_link'] ?? '' ?>',
                                                        '<?= $member['linkedin_link'] ?? '' ?>',
                                                        '<?= $member['telegram_link'] ?? '' ?>'
                                                    )">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="post" action="manage-about-us.php" class="d-inline">
                                                <input type="hidden" name="action" value="delete_team_member">
                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این عضو مطمئن هستید؟')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="manage-about-us.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_team_member">

                    <div class="modal-header">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; left: 10px;"></button>
                        <h5 class="modal-title" id="addMemberModalLabel" style="margin-left: 40px;">افزودن عضو جدید به تیم</h5>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="avatar" class="form-label">تصویر عضو</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                <small class="text-muted">تصویر مربعی با ابعاد حداقل 300x300 پیکسل</small>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">نام کامل</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="position" class="form-label">سمت</label>
                                    <input type="text" class="form-control" id="position" name="position" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="bio" class="form-label">توضیحات (بیوگرافی)</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="contact_email" class="form-label">ایمیل</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email">
                            </div>
                            <div class="col-md-6">
                                <label for="contact_phone" class="form-label">تلفن</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone">
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3">شبکه های اجتماعی</h6>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-3">
                                <label for="member_instagram" class="form-label">اینستاگرام</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                                    <input type="text" class="form-control" id="member_instagram" name="member_instagram" placeholder="لینک خود را وارد کنید" onfocus="this.value=''" value="https://instagram.com/">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="member_linkedin" class="form-label">لینکدین</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-linkedin"></i></span>
                                    <input type="text" class="form-control" id="member_linkedin" name="member_linkedin" placeholder="لینک خود را وارد کنید" onfocus="this.value=''" value="https://linkedin.com/in/">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="member_telegram" class="form-label">تلگرام</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-telegram"></i></span>
                                    <input type="text" class="form-control" id="member_telegram" name="member_telegram" placeholder="لینک خود را وارد کنید" onfocus="this.value=''" value="https://t.me/">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ذخیره عضو جدید</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="manage-about-us.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_team_member">
                    <input type="hidden" name="member_id" id="edit_member_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="editMemberModalLabel">ویرایش عضو تیم</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; left: 10px;"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_avatar" class="form-label">تصویر عضو</label>
                                <input type="file" class="form-control" id="edit_avatar" name="avatar" accept="image/*">
                                <div id="current_avatar" class="mt-2 text-center"></div>
                                <small class="text-muted">تصویر مربعی با ابعاد حداقل 300x300 پیکسل</small>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="edit_name" class="form-label">نام کامل</label>
                                    <input type="text" class="form-control" id="edit_name" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_position" class="form-label">سمت</label>
                                    <input type="text" class="form-control" id="edit_position" name="position" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_bio" class="form-label">توضیحات (بیوگرافی)</label>
                            <textarea class="form-control" id="edit_bio" name="bio" rows="3"></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_contact_email" class="form-label">ایمیل</label>
                                <input type="email" class="form-control" id="edit_contact_email" name="contact_email">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_phone" class="form-label">تلفن</label>
                                <input type="text" class="form-control" id="edit_contact_phone" name="contact_phone">
                            </div>
                        </div>

                        <h6 class="mt-4 mb-3">شبکه های اجتماعی</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_member_instagram" class="form-label">اینستاگرام</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                                    <input type="text" class="form-control" id="edit_member_instagram" name="member_instagram" placeholder="لینک خود را وارد کنید" onfocus="this.value=''" value="https://instagram.com/">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_member_linkedin" class="form-label">لینکدین</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-linkedin"></i></span>
                                    <input type="text" class="form-control" id="edit_member_linkedin" name="member_linkedin" placeholder="لینک خود را وارد کنید" onfocus="this.value=''" value="https://linkedin.com/in/">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_member_telegram" class="form-label">تلگرام</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fab fa-telegram"></i></span>
                                    <input type="text" class="form-control" id="edit_member_telegram" name="member_telegram" placeholder="لینک خود را وارد کنید" onfocus="this.value=''" value="https://t.me/">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <a href="admin-panel.php" class="back-to-panel" title="بازگشت به پنل مدیریت">
        <i class="fas fa-arrow-left"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function formatText(command, value = null) {
            const editor = document.getElementById('description');
            document.execCommand(command, false, value);
            editor.focus();

            document.getElementById('description-textarea').value = editor.innerHTML;
        }

        document.getElementById('description').addEventListener('input', function() {
            document.getElementById('description-textarea').value = this.innerHTML;
        });

        function editMember(id, name, position, bio, contact_email, contact_phone, instagram, linkedin, telegram) {
            document.getElementById('edit_member_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_position').value = position;
            document.getElementById('edit_bio').value = bio;
            document.getElementById('edit_contact_email').value = contact_email || '';
            document.getElementById('edit_contact_phone').value = contact_phone || '';
            document.getElementById('edit_member_instagram').value = instagram || '';
            document.getElementById('edit_member_linkedin').value = linkedin || '';
            document.getElementById('edit_member_telegram').value = telegram || '';

            const currentAvatar = document.getElementById('current_avatar');
            const avatarUrl = document.querySelector(`tr td img[alt="${name}"]`)?.src;

            if (avatarUrl) {
                currentAvatar.innerHTML = `
                    <img src="${avatarUrl}" alt="تصویر فعلی" class="img-thumbnail" style="max-height: 100px;">
                    <div class="mt-2 text-muted">تصویر فعلی</div>
                `;
            } else {
                currentAvatar.innerHTML = `
                    <div class="team-member-avatar bg-secondary d-flex align-items-center justify-content-center mx-auto">
                        <i class="fas fa-user text-white" style="font-size: 2rem;"></i>
                    </div>
                    <div class="mt-2 text-muted">تصویر فعلی</div>
                `;
            }

            const modal = new bootstrap.Modal(document.getElementById('editMemberModal'));
            modal.show();
        }

        document.documentElement.style.setProperty('--primary-color', '<?= isset($settings['button_color']) ? $settings['button_color'] : '#4e73df' ?>');
    </script>
</body>

</html>