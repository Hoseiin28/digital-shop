<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}


$backupConfig = [
    'backup_dir' => __DIR__ . '/../database/backup/',
    'log_file' => __DIR__ . '/../database/backup/backup_log.txt',
    'max_backups' => 20,
    'db_name' => 'digital_shop',
    'compress' => true,
    'backup_schedule' => [
        'daily' => true,
        'weekly' => true,
        'monthly' => true
    ]
];

if (!is_dir($backupConfig['backup_dir'])) {
    mkdir($backupConfig['backup_dir'], 0755, true);
}

function logAction($message, $logFile)
{
    $time = date('Y-m-d H:i:s');
    $logMessage = "[$time] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    return $logMessage;
}

function backupDatabase($pdo, $config, $options = [])
{
    try {
        $defaultOptions = [
            'include_data' => true,
            'include_triggers' => true,
            'include_routines' => true,
            'include_events' => true,
            'compress' => $config['compress']
        ];

        $options = array_merge($defaultOptions, $options);

        $sqlDump = "-- MySQL Dump\n";
        $sqlDump .= "-- Host: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "\n";
        $sqlDump .= "-- Generation Time: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- PHP Version: " . phpversion() . "\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sqlDump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sqlDump .= "SET AUTOCOMMIT = 0;\n";
        $sqlDump .= "START TRANSACTION;\n";
        $sqlDump .= "SET time_zone = '+00:00';\n\n";

        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            $sqlDump .= "--\n-- Table structure for table `$table`\n--\n";
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlDump .= $createTable['Create Table'] . ";\n\n";

            if ($options['include_data']) {
                $rowCount = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                if ($rowCount > 0) {
                    $sqlDump .= "--\n-- Dumping data for table `$table`\n--\n";

                    $batchSize = 1000;
                    $batches = ceil($rowCount / $batchSize);

                    for ($i = 0; $i < $batches; $i++) {
                        $offset = $i * $batchSize;
                        $data = $pdo->query("SELECT * FROM `$table` LIMIT $offset, $batchSize")->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($data as $row) {
                            $values = array_map(function ($value) use ($pdo) {
                                return $value === null ? 'NULL' : $pdo->quote($value);
                            }, array_values($row));

                            $sqlDump .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                        }
                    }
                    $sqlDump .= "\n";
                }
            }
        }

        if ($options['include_triggers']) {
            $sqlDump .= "--\n-- Dumping triggers\n--\n";
            $triggers = $pdo->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);

            if (count($triggers) > 0) {
                foreach ($triggers as $trigger) {
                    $sqlDump .= "DROP TRIGGER IF EXISTS `{$trigger['Trigger']}`;\n";
                    $sqlDump .= "DELIMITER //\n";
                    $sqlDump .= "CREATE TRIGGER `{$trigger['Trigger']}` {$trigger['Timing']} {$trigger['Event']} ON `{$trigger['Table']}` FOR EACH ROW\n";
                    $sqlDump .= "{$trigger['Statement']}//\n";
                    $sqlDump .= "DELIMITER ;\n\n";
                }
            } else {
                $sqlDump .= "-- No triggers found\n\n";
            }
        }

        if ($options['include_routines']) {
            $sqlDump .= "--\n-- Dumping routines\n--\n";

            $procedures = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = '{$config['db_name']}'")->fetchAll(PDO::FETCH_ASSOC);
            if (count($procedures) > 0) {
                foreach ($procedures as $proc) {
                    $stmt = $pdo->query("SHOW CREATE PROCEDURE `{$proc['Name']}`");
                    $createProc = $stmt->fetch(PDO::FETCH_ASSOC);
                    $sqlDump .= "DROP PROCEDURE IF EXISTS `{$proc['Name']}`;\n";
                    $sqlDump .= "DELIMITER //\n";
                    $sqlDump .= $createProc['Create Procedure'] . "//\n";
                    $sqlDump .= "DELIMITER ;\n\n";
                }
            } else {
                $sqlDump .= "-- No procedures found\n\n";
            }

            $functions = $pdo->query("SHOW FUNCTION STATUS WHERE Db = '{$config['db_name']}'")->fetchAll(PDO::FETCH_ASSOC);
            if (count($functions) > 0) {
                foreach ($functions as $func) {
                    $stmt = $pdo->query("SHOW CREATE FUNCTION `{$func['Name']}`");
                    $createFunc = $stmt->fetch(PDO::FETCH_ASSOC);
                    $sqlDump .= "DROP FUNCTION IF EXISTS `{$func['Name']}`;\n";
                    $sqlDump .= "DELIMITER //\n";
                    $sqlDump .= $createFunc['Create Function'] . "//\n";
                    $sqlDump .= "DELIMITER ;\n\n";
                }
            } else {
                $sqlDump .= "-- No functions found\n\n";
            }
        }

        if ($options['include_events']) {
            $sqlDump .= "--\n-- Dumping events\n--\n";
            $events = $pdo->query("SHOW EVENTS")->fetchAll(PDO::FETCH_ASSOC);

            if (count($events) > 0) {
                foreach ($events as $event) {
                    $stmt = $pdo->query("SHOW CREATE EVENT `{$event['Name']}`");
                    $createEvent = $stmt->fetch(PDO::FETCH_ASSOC);
                    $sqlDump .= "DROP EVENT IF EXISTS `{$event['Name']}`;\n";
                    $sqlDump .= "DELIMITER //\n";
                    $sqlDump .= $createEvent['Create Event'] . "//\n";
                    $sqlDump .= "DELIMITER ;\n\n";
                }
            } else {
                $sqlDump .= "-- No events found\n\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sqlDump .= "COMMIT;\n";

        $backupType = $options['include_data'] ? 'full' : 'structure';
        $filename = "{$config['db_name']}_{$backupType}_" . date('Y-m-d_His') . ".sql";
        $filepath = $config['backup_dir'] . $filename;

        file_put_contents($filepath, $sqlDump);

        if ($options['compress']) {
            $compressedFile = $filepath . '.gz';
            $gzipSuccess = file_put_contents(
                'compress.zlib://' . $compressedFile,
                file_get_contents($filepath)
            );

            if ($gzipSuccess) {
                unlink($filepath);
                $filename .= '.gz';
                $filepath = $compressedFile;
            }
        }

        $logMessage = "Backup created: $filename (" .
            ($options['include_data'] ? 'with data' : 'structure only') .
            ($options['compress'] ? ', compressed' : '') . ")";

        logAction($logMessage, $config['log_file']);

        cleanupOldBackups($config);

        return [
            'filename' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath),
            'type' => $backupType,
            'compressed' => $options['compress']
        ];
    } catch (PDOException $e) {
        logAction("Backup failed: " . $e->getMessage(), $config['log_file']);
        return false;
    }
}

function cleanupOldBackups($config)
{
    $backupFiles = glob($config['backup_dir'] . "*.sql*");

    if (count($backupFiles) > $config['max_backups']) {
        usort($backupFiles, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $filesToDelete = array_slice($backupFiles, 0, count($backupFiles) - $config['max_backups']);
        foreach ($filesToDelete as $file) {
            unlink($file);
            logAction("Deleted old backup: " . basename($file), $config['log_file']);
        }
    }
}

function restoreDatabase($pdo, $backupFile, $config)
{
    try {
        if (!file_exists($backupFile)) {
            throw new Exception("Backup file not found");
        }

        $isCompressed = pathinfo($backupFile, PATHINFO_EXTENSION) === 'gz';

        if ($isCompressed) {
            $sql = file_get_contents('compress.zlib://' . $backupFile);
        } else {
            $sql = file_get_contents($backupFile);
        }

        if (!$sql) {
            throw new Exception("Failed to read backup file");
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        $pdo->exec($sql);

        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        logAction("Database restored from: " . basename($backupFile), $config['log_file']);
        return true;
    } catch (Exception $e) {
        logAction("Restore failed: " . $e->getMessage(), $config['log_file']);
        return false;
    }
}

function resetDatabase($pdo, $config, $tableName = null)
{
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        if ($tableName) {
            if (in_array($tableName, $tables)) {
                $pdo->exec("TRUNCATE TABLE `$tableName`");
                logAction("Table truncated: $tableName", $config['log_file']);
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                logAction("Table $tableName reset successfully", $config['log_file']);
                return true;
            } else {
                throw new Exception("Table $tableName not found");
            }
        } else {
            foreach ($tables as $table) {
                $pdo->exec("TRUNCATE TABLE `$table`");
                logAction("Table truncated: $table", $config['log_file']);
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            logAction("Database reset successfully", $config['log_file']);
            return true;
        }
    } catch (Exception $e) {
        logAction("Database reset failed: " . $e->getMessage(), $config['log_file']);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        return false;
    }
}

function downloadBackup($filename, $backupDir)
{
    $filepath = $backupDir . $filename;

    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    return false;
}

function deleteBackup($filename, $backupDir, $logFile)
{
    $filepath = $backupDir . $filename;

    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            logAction("Backup deleted: $filename", $logFile);
            return true;
        }
    }

    return false;
}

function getBackupFiles($backupDir)
{
    $files = array_filter(scandir($backupDir), function ($file) use ($backupDir) {
        return is_file($backupDir . $file) && preg_match('/\.sql(\.gz)?$/i', $file);
    });

    $backups = [];
    foreach ($files as $file) {
        $filepath = $backupDir . $file;
        $backups[] = [
            'filename' => $file,
            'path' => $filepath,
            'size' => filesize($filepath),
            'modified' => filemtime($filepath),
            'type' => strpos($file, '_full_') !== false ? 'full' : 'structure',
            'compressed' => pathinfo($file, PATHINFO_EXTENSION) === 'gz'
        ];
    }

    usort($backups, function ($a, $b) {
        return $b['modified'] - $a['modified'];
    });

    return $backups;
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$message = '';
$messageType = '';
$downloadLink = '';

if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $fileToDelete = basename($_GET['delete']);
    if (deleteBackup($fileToDelete, $backupConfig['backup_dir'], $backupConfig['log_file'])) {
        $_SESSION['message'] = "فایل بکاپ '$fileToDelete' با موفقیت حذف شد.";
        $_SESSION['messageType'] = 'success';
    } else {
        $_SESSION['message'] = "حذف فایل بکاپ '$fileToDelete' انجام نشد یا فایل وجود ندارد.";
        $_SESSION['messageType'] = 'error';
    }
    header('Location: backup.php');
    exit;
}

if (isset($_GET['download']) && !empty($_GET['download'])) {
    $fileToDownload = basename($_GET['download']);
    downloadBackup($fileToDownload, $backupConfig['backup_dir']);
    exit;
}

if (isset($_POST['restore']) && !empty($_POST['backup_file'])) {
    $fileToRestore = $backupConfig['backup_dir'] . basename($_POST['backup_file']);
    $result = restoreDatabase($pdo, $fileToRestore, $backupConfig);

    if ($result) {
        $_SESSION['message'] = "دیتابیس با موفقیت از فایل " . basename($fileToRestore) . " بازگردانی شد.";
        $_SESSION['messageType'] = 'success';
    } else {
        $_SESSION['message'] = "خطا در بازگردانی دیتابیس از فایل " . basename($fileToRestore);
        $_SESSION['messageType'] = 'error';
    }

    header('Location: backup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $includeTriggers = isset($_POST['include_triggers']);
    $includeRoutines = isset($_POST['include_routines']);
    $includeEvents = isset($_POST['include_events']);

    if (isset($_POST['reset_database'])) {
        $resetOption = $_POST['reset_option'] ?? 'full';
        $tableName = $_POST['table_name'] ?? null;

        if ($resetOption === 'table' && $tableName) {
            $result = resetDatabase($pdo, $backupConfig, $tableName);
            if ($result) {
                $_SESSION['message'] = "جدول " . htmlspecialchars($tableName) . " با موفقیت ریست شد و تمام داده‌های آن حذف شدند.";
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = "خطا در ریست جدول " . htmlspecialchars($tableName) . "، لطفا مجددا تلاش کنید.";
                $_SESSION['messageType'] = 'error';
            }
        } else {
            $result = resetDatabase($pdo, $backupConfig);
            if ($result) {
                $_SESSION['message'] = "دیتابیس با موفقیت ریست شد و تمام داده‌ها حذف شدند.";
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = "خطا در ریست دیتابیس، لطفا مجددا تلاش کنید.";
                $_SESSION['messageType'] = 'error';
            }
        }

        header('Location: backup.php');
        exit;
    } elseif (isset($_POST['backup_structure'])) {
        $result = backupDatabase($pdo, $backupConfig, [
            'include_data' => false,
            'include_triggers' => $includeTriggers,
            'include_routines' => $includeRoutines,
            'include_events' => $includeEvents
        ]);

        if ($result !== false) {
            $_SESSION['message'] = "بکاپ ساختار با موفقیت ایجاد شد." .
                ($includeTriggers ? " (شامل تریگرها)" : "") .
                ($includeRoutines ? " (شامل روال‌ها)" : "") .
                ($includeEvents ? " (شامل ایونت‌ها)" : "");
            $_SESSION['messageType'] = 'success';
            $_SESSION['downloadLink'] = 'backups/' . $result['filename'];
        } else {
            $_SESSION['message'] = "خطا در ایجاد بکاپ ساختار، لطفا مجددا تلاش کنید.";
            $_SESSION['messageType'] = 'error';
        }
    } else {
        $result = backupDatabase($pdo, $backupConfig, [
            'include_data' => true,
            'include_triggers' => $includeTriggers,
            'include_routines' => $includeRoutines,
            'include_events' => $includeEvents
        ]);

        if ($result !== false) {
            $_SESSION['message'] = "بکاپ کامل با موفقیت ایجاد شد." .
                ($includeTriggers ? " (شامل تریگرها)" : "") .
                ($includeRoutines ? " (شامل روال‌ها)" : "") .
                ($includeEvents ? " (شامل ایونت‌ها)" : "");
            $_SESSION['messageType'] = 'success';
            $_SESSION['downloadLink'] = 'backups/' . $result['filename'];
        } else {
            $_SESSION['message'] = "خطا در ایجاد بکاپ کامل، لطفا مجددا تلاش کنید.";
            $_SESSION['messageType'] = 'error';
        }
    }

    header('Location: backup.php');
    exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? '';
    $downloadLink = $_SESSION['downloadLink'] ?? '';
    unset($_SESSION['message'], $_SESSION['messageType'], $_SESSION['downloadLink']);
}

$backupFiles = getBackupFiles($backupConfig['backup_dir']);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پشتیبان‌گیری</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-backup.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>

    <style>
        :root {
            --primary-color: <?= $settings['button_color'] ?? '#4e73df' ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: <?= $settings['primary_color_dark'] ?? '#3a5bcd' ?>;
            border-color: <?= $settings['primary_color_dark'] ?? '#3a5bcd' ?>;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-database me-2"></i>
                    مدیریت پشتیبان‌گیری
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه من') ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <a href="admin-panel.php" class="back-to-panel" title="بازگشت به پنل مدیریت">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <?php if ($downloadLink): ?>
                <a href="<?= htmlspecialchars($downloadLink) ?>" download class="download-link">
                    <i class="fas fa-download me-2"></i>دانلود فایل بکاپ
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>عملیات پشتیبان‌گیری</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="d-grid gap-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="includeTriggers" name="include_triggers" checked>
                                        <label class="form-check-label" for="includeTriggers">شامل تریگرها</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="includeRoutines" name="include_routines" checked>
                                        <label class="form-check-label" for="includeRoutines">شامل روال‌ها</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="includeEvents" name="include_events" checked>
                                        <label class="form-check-label" for="includeEvents">شامل ایونت‌ها</label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-database me-2"></i>بکاپ کامل (داده + ساختار)
                                </button>
                                <button type="submit" name="backup_structure" class="btn btn-info btn-lg">
                                    <i class="fas fa-code me-2"></i>بکاپ ساختار (بدون داده)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>بازگردانی دیتابیس</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>هشدار:</strong> این عملیات تمام داده‌های فعلی دیتابیس را با داده‌های بکاپ جایگزین می‌کند!
                        </div>

                        <form method="post" class="restore-form" onsubmit="return confirm('آیا مطمئن هستید؟ تمام داده‌های فعلی دیتابیس با داده‌های بکاپ جایگزین خواهند شد!');">
                            <select name="backup_file" class="form-select" required size="5">
                                <option value="">-- انتخاب فایل بکاپ --</option>
                                <?php foreach ($backupFiles as $backup): ?>
                                    <option value="<?= htmlspecialchars($backup['filename']) ?>">
                                        <?= htmlspecialchars($backup['filename']) ?>
                                        (<?= date('Y/m/d H:i', $backup['modified']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="restore" class="btn btn-primary mt-2">
                                <i class="fas fa-undo me-1"></i> بازگردانی
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>عملیات ریست</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-radiation me-2"></i>
                            <strong>هشدار شدید:</strong> این عملیات داده‌های دیتابیس را حذف می‌کند و قابل بازگشت نیست!
                        </div>

                        <form method="post" onsubmit="return confirmReset();">
                            <div class="mb-3">
                                <label for="resetOption" class="form-label">نوع ریست:</label>
                                <select class="form-select" id="resetOption" name="reset_option" onchange="toggleTableSelect()">
                                    <option value="full">ریست کامل تمام دیتابیس</option>
                                    <option value="table">ریست جدول خاص</option>
                                </select>
                            </div>

                            <div class="mb-3" id="tableSelectContainer" style="display: none;">
                                <label for="tableName" class="form-label">انتخاب جدول:</label>
                                <select class="form-select" id="tableName" name="table_name" size="5">
                                    <?php
                                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                                    foreach ($tables as $table) {
                                        echo "<option value='$table'>$table</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <button type="submit" name="reset_database" class="btn btn-danger btn-lg w-100">
                                <i class="fas fa-trash-alt me-2"></i>اجرای عملیات ریست
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>وضعیت سیستم</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-database me-2"></i> نام دیتابیس</span>
                                <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($backupConfig['db_name']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-folder me-2"></i> محل ذخیره بکاپ</span>
                                <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($backupConfig['backup_dir']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-file-alt me-2"></i> تعداد فایل‌های بکاپ</span>
                                <span class="badge bg-primary rounded-pill"><?= count($backupFiles) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-hdd me-2"></i> فضای استفاده شده</span>
                                <span class="badge bg-primary rounded-pill">
                                    <?php
                                    $totalSize = array_reduce($backupFiles, function ($carry, $item) {
                                        return $carry + $item['size'];
                                    }, 0);
                                    echo round($totalSize / (1024 * 1024), 2) . ' MB';
                                    ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock me-2"></i> آخرین بکاپ</span>
                                <span class="badge bg-primary rounded-pill">
                                    <?= count($backupFiles) > 0 ? date('Y/m/d H:i', $backupFiles[0]['modified']) : 'ندارد' ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-archive me-2"></i>بکاپ‌های موجود</h5>
                    <span class="badge bg-primary rounded-pill"><?= count($backupFiles) ?> فایل</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($backupFiles) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>نام فایل</th>
                                    <th>نوع</th>
                                    <th>حجم</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backupFiles as $backup): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-file-<?= $backup['compressed'] ? 'archive' : 'code' ?> me-2"></i>
                                            <?= htmlspecialchars($backup['filename']) ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-type badge-<?= $backup['type'] ?>">
                                                <?= $backup['type'] === 'full' ? 'کامل' : 'ساختار' ?>
                                            </span>
                                            <?php if ($backup['compressed']): ?>
                                                <span class="badge badge-type badge-compressed">فشرده</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= round($backup['size'] / 1024, 2) ?> KB</td>
                                        <td><?= date('Y/m/d H:i', $backup['modified']) ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="backup.php?download=<?= urlencode($backup['filename']) ?>"
                                                    class="btn btn-sm btn-primary" title="دانلود">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <form method="get" onsubmit="return confirm('آیا از حذف این بکاپ مطمئن هستید؟');">
                                                    <input type="hidden" name="delete" value="<?= htmlspecialchars($backup['filename']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">هیچ فایل بکاپی یافت نشد</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        function toggleTableSelect() {
            const resetOption = document.getElementById('resetOption').value;
            const tableSelectContainer = document.getElementById('tableSelectContainer');

            if (resetOption === 'table') {
                tableSelectContainer.style.display = 'block';
            } else {
                tableSelectContainer.style.display = 'none';
            }
        }

        function confirmReset() {
            const resetOption = document.getElementById('resetOption').value;
            let message = 'آیا مطمئن هستید؟ ';

            if (resetOption === 'full') {
                message += 'تمام داده‌های دیتابیس حذف خواهند شد!';
            } else {
                const tableName = document.getElementById('tableName').value;
                message += `تمام داده‌های جدول ${tableName} حذف خواهند شد!`;
            }

            return confirm(message);
        }
    </script>
</body>

</html>