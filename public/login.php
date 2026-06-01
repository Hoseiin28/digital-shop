<?php
session_start();
require_once 'config.php';

if (isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                $updateStmt = $pdo->prepare("UPDATE Users SET last_activity = UNIX_TIMESTAMP() WHERE id = :id");
                $updateStmt->execute(['id' => $user['id']]);

                echo '<script>window.location.href = "../index.php";</script>';
                exit;
            } else {
                $login_error = "ایمیل یا رمز عبور اشتباه است";
            }
        } catch (PDOException $e) {
            $login_error = "خطا در ورود به سیستم";
        }
    } else {
        $login_error = "لطفا ایمیل و رمز عبور را وارد کنید";
    }
}

if (isset($_POST['register'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $password = $_POST['password'] ?? '';
    $re_password = $_POST['re-password'] ?? '';

    $errors = [];

    if (empty($name)) $errors[] = "نام الزامی است";
    if (empty($email)) $errors[] = "ایمیل الزامی است";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "فرمت ایمیل نامعتبر است";
    if (empty($password)) $errors[] = "رمز عبور الزامی است";

    $password = trim($_POST['password']);
    $re_password = trim($_POST['re-password']);

    if ($password !== $re_password) {
        $errors[] = "رمز عبور و تکرار آن مطابقت ندارند";
    }

    $avatar = 'image/default-avatar.svg';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowed)) {
            $uploadDir = '../image/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $fileName = uniqid('avatar_') . '.' . $fileExt;
            $uploadPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                $avatar = $uploadPath;
            }
        } else {
            $errors[] = "فرمت فایل تصویر نامعتبر است (فقط jpg, jpeg, png مجاز هستند)";
        }
    }

    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM Users WHERE email = :email");
            $checkStmt->execute(['email' => $email]);

            if ($checkStmt->rowCount() > 0) {
                $errors[] = "این ایمیل قبلاً ثبت شده است";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare("
                                INSERT INTO Users (name, email, password, phone, address, avatar, role) 
                                VALUES (:name, :email, :password, :phone, :address, :avatar, 'user')
                            ");

                $insertStmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'phone' => $phone,
                    'address' => $address,
                    'avatar' => $avatar
                ]);

                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = 'user';

                echo '<script>window.location.href = "../index.php";</script>';
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "خطا در ثبت نام: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $register_error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود | ثبت نام</title>
    <link rel="icon" href="../iamge/default-avatar.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,300;0,400;0,500;0,600;0,700;0,900;1,700;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-login.css">
</head>

<body>

    <main class="main">
        <div class="container">
            <div class="forms">
                <div class="sign__blog">
                    <form method="post" action="" class="signin">
                        <div class="profile__img__blog">
                            <img src="../image/default-avatar.svg" alt="" class="profile">
                        </div>
                        <h2 class="form_title">ورود به حساب کاربری</h2>

                        <?php if (!empty($login_error)): ?>
                            <div class="error-message"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                            <br>
                        <div class="input_item">
                            <input name="email" type="email" class="form_input form_email" oninvalid="this.classList.add('invalid')" required>
                            <label for="email" class="form_label"><i class="fas fa-envelope"></i> ایمیل</label>
                            <p class="error_email">فرمت ایمیل نامعتبر است</p>
                        </div><br>
                        <div class="input_item">
                            <span class="passwordEye"><i class="fas fa-eye-slash"></i></span>
                            <input name="password" type="password" class="form_input form_pass" oninvalid="this.classList.add('invalid')" required>
                            <label for="password" class="form_label"><i class="fas fa-lock"></i> رمز عبور</label>
                        </div>
                        <button type="submit" name="login" class="btn">ورود</button>
                    </form>

                    <form method="post" action="" class="register" enctype="multipart/form-data">
                        <h2 class="form_title">ثبت نام</h2>

                        <?php if (!empty($register_error)): ?>
                            <div class="error-message"><?php echo $register_error; ?></div>
                        <?php endif; ?>

                        <div class="form_steps">
                            <div class="steps">
                                <p class="steps_title">اطلاعات شخصی</p>
                                <span class="steps_numb">1</span>
                            </div>
                            <div class="steps">
                                <p class="steps_title">رمز عبور</p>
                                <span class="steps_numb">2</span>
                            </div>
                            <div class="steps">
                                <p class="steps_title">تصویر پروفایل</p>
                                <span class="steps_numb step_end">3</span>
                            </div>
                        </div>
                        <div class="register_content">
                            <div class="form_pages">
                                <div class="form_page page_1">
                                    <div class="input_item">
                                        <input name="name" type="text" class="form_input" oninvalid="this.classList.add('invalid')" required>
                                        <label for="name" class="form_label"><i class="fas fa-user"></i> نام کامل</label>
                                    </div>
                                    <div class="input_item">
                                        <input name="email" type="email" class="form_input form_email" oninvalid="this.classList.add('invalid')" required>
                                        <label for="email" class="form_label"><i class="fas fa-envelope"></i> ایمیل</label>
                                        <p class="error_email">فرمت ایمیل نامعتبر است</p>
                                    </div>
                                    <div class="input_item">
                                        <input name="phone" type="tel" class="form_input" oninvalid="this.classList.add('invalid')">
                                        <label for="phone" class="form_label"><i class="fas fa-phone"></i> شماره تماس</label>
                                    </div>
                                    <div class="input_item textarea_item">
                                        <textarea name="address" maxlength="300" type="text" class="form_input form_textarea" oninvalid="this.classList.add('invalid')" required></textarea>
                                        <label for="address" class="form_label"><i class="fas fa-map-marker-alt"></i> آدرس</label>
                                    </div>
                                    <button type="button" class="btn nextBtn">مرحله بعد</button>
                                </div>
                                <div class="form_page page_2 page_password">
                                    <div class="input_item password_item">
                                        <span class="passwordEye"><i class="fas fa-eye-slash"></i></span>
                                        <input name="password" type="password" class="form_input form_pass form_password" oninvalid="this.classList.add('invalid')" tabindex="-1" required>
                                        <label for="password" class="form_label"><i class="fas fa-lock"></i> رمز عبور</label>
                                    </div>
                                    <div class="password__content">
                                        <div class="password__indicator">
                                            <span></span>
                                        </div>
                                        <ul class="password__info">
                                            <li class="password__info__text"><i class="fas fa-check-circle" id="passWeak"></i> حداقل 6 کاراکتر</li>
                                            <li class="password__info__text"><i class="fas fa-check-circle" id="passMedium"></i> حداقل 1 عدد</li>
                                            <li class="password__info__text"><i class="fas fa-check-circle" id="passStrong"></i> حداقل 1 حرف بزرگ</li>
                                        </ul>
                                    </div>
                                    <div class="input_item password_item">
                                        <span class="passwordEye"><i class="fas fa-eye-slash"></i></span>
                                        <input name="re-password" type="password" class="form_input form_pass form_password" oninvalid="this.classList.add('invalid')" tabindex="-1" required>
                                        <label for="password" class="form_label"><i class="fas fa-lock"></i> تکرار رمز عبور</label>
                                        <p class="error_pass">رمزهای عبور مطابقت ندارند</p>
                                    </div>
                                    <div class="register_buttons">
                                        <button type="button" class="btn backBtn" tabindex="-1">مرحله قبل</button>
                                        <button type="button" class="btn nextBtn" tabindex="-1">مرحله بعد</button>
                                    </div>
                                </div>
                                <div class="form_page page_3">
                                    <div class="input_item input_uploader">
                                        <div class="form__imgUploader">
                                            <div class="form__wrapper">
                                                <div class="form__image">
                                                    <img src="" alt="" class="form__img">
                                                </div>
                                                <div class="formUploader__content">
                                                    <div class="formUploader__icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                                    <div class="formUploader__text">هنوز تصویری انتخاب نشده است!(اختیاری)</div>
                                                </div>
                                                <div class="formUploader__cancel"><i class="fas fa-times"></i></div>
                                                <div class="formUploader__fileName">
                                                    <p>نام فایل</p>
                                                </div>
                                            </div>
                                            <input name="photo" type="file" class="imgUploader" accept=".jpg, .jpeg, .png" name="avatar" tabindex="-1" hidden>
                                        </div>
                                    </div>
                                    <div class="register_buttons">
                                        <button type="button" class="btn backBtn" tabindex="-1">مرحله قبل</button>
                                        <button type="submit" name="register" class="btn register_button" tabindex="-1">ثبت نام</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="invalid_info">مقادیر وارد شده نامعتبر هستند! لطفا بررسی کنید</p>
                    </form>
                </div>
            </div>

            <div class="panels__blog">
                <div class="panel left__panel">
                    <div class="content">
                        <h3 class="panel__title">کاربر جدید هستید؟</h3>
                        <p class="panel__text">برای استفاده از امکانات سایت، لطفا ثبت نام کنید</p>
                        <button class="button transparent" id="register__btn">ثبت نام</button>
                    </div>
                    <img src="../image/login/undraw_Login_re_4vu2.svg" alt="" class="panel__img">
                </div>

                <div class="panel right__panel">
                    <div class="content">
                        <h3 class="panel__title">حساب کاربری دارید؟</h3>
                        <p class="panel__text">برای ورود به حساب کاربری خود، اطلاعات خود را وارد کنید</p>
                        <button class="button transparent" id="signin__btn">ورود</button>
                    </div>
                    <img src="../image/login/undraw_authentication_fsn5.svg" alt="" class="panel__img">
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/script-login.js"></script>
</body>

</html>