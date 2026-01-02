<?php
session_start();
require_once '../app/config/config.php';
require_once '../app/models/Language.php';

// Dil seçimi yapıldıysa kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
}

// Varsayılan dil veya seçilen dili yükle
$currentLang = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
Language::init($currentLang);

if (file_exists('../app/config/installed.php')) {
    header('Location: ../login.php');
    exit;
}

$step = isset($_SESSION['step']) ? $_SESSION['step'] : 1;
$error = '';

// Geri butonu için kontrol
if (isset($_POST['back'])) {
    $_SESSION['step'] = max(1, $step - 1);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Form değerlerini session'da sakla
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $_SESSION['form_data'] = [
            'host' => $_POST['host'] ?? '',
            'dbuser' => $_POST['dbuser'] ?? '',
            'dbpass' => $_POST['dbpass'] ?? '',
            'dbname' => $_POST['dbname'] ?? ''
        ];
    }
}

// Session'dan form değerlerini al
$formData = $_SESSION['form_data'] ?? [
    'host' => '',
    'dbuser' => '',
    'dbpass' => '',
    'dbname' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            if (isset($_POST['language'])) {
                $_SESSION['step'] = 2;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            break;

        case 2:
            // Veritabanı bilgilerini test et ve kaydet
            if (!isset($_POST['back'])) { // Geri butonuna basılmadıysa devam et
                $dbhost = $_POST['host'];
                $dbuser = $_POST['dbuser'];
                $dbpass = $_POST['dbpass'];
                $dbname = $_POST['dbname'];

                try {
                    $conn = new PDO("mysql:host=$dbhost", $dbuser, $dbpass);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Veritabanını oluştur veya kontrol et
                    $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
                    $conn->exec("USE `$dbname`");

                    // Config dosyasını güncelle
                    $config = file_get_contents('../app/config/config.php');
                    $config = str_replace("define('DB_HOST', '');", "define('DB_HOST', '$dbhost');", $config);
                    $config = str_replace("define('DB_USER', '');", "define('DB_USER', '$dbuser');", $config);
                    $config = str_replace("define('DB_PASS', '');", "define('DB_PASS', '$dbpass');", $config);
                    $config = str_replace("define('DB_NAME', '');", "define('DB_NAME', '$dbname');", $config);

                    file_put_contents('../app/config/config.php', $config);

                    // Admin tablosunu oluştur
                    $sql = "CREATE TABLE IF NOT EXISTS admin_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        password_hash VARCHAR(255) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                    $conn->exec($sql);

                    $_SESSION['step'] = 3;
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $error = Language::get('error_db_connection') . $e->getMessage();
                }
            }
            break;

        case 3:
            // Admin şifresini kaydet
            if ($_POST['password'] !== $_POST['password_confirm']) {
                $error = Language::get('error_password_match');
            } elseif (strlen($_POST['password']) < 6) {
                $error = Language::get('error_password_length');
            } else {
                try {
                    // Şifreyi config dosyasına kaydet
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $config = file_get_contents('../app/config/config.php');
                    $config = str_replace(
                        "define('ADMIN_PASSWORD_HASH', '');",
                        "define('ADMIN_PASSWORD_HASH', '$password_hash');",
                        $config
                    );
                    file_put_contents('../app/config/config.php', $config);

                    // Kurulumu tamamla
                    file_put_contents('../app/config/installed.php', '<?php return true;');

                    header('Location: ../login.php');
                    exit;
                } catch (Exception $e) {
                    $error = Language::get('error') . ': ' . $e->getMessage();
                }
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Runik MySQL Backup - <?php echo Language::get('installation'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body class="hold-transition login-page">
    <div class="login-box" style="width: 450px;">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h1><b>Runik </b>MySQL Backup</h1>
            </div>
            <div class="card-body">
                <p class="login-box-msg">
                    <?php
                    switch ($step) {
                        case 1:
                            echo Language::get('language_selection');
                            break;
                        case 2:
                            echo Language::get('database_settings');
                            break;
                        case 3:
                            echo Language::get('admin_password');
                            break;
                    }
                    ?>
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="progress mb-3">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $step * 33.33; ?>%"></div>
                </div>

                <?php if ($step === 1): ?>
                    <form method="post">
                        <div class="form-group">
                            <label id="language_label"><?php echo Language::get('select_language'); ?></label>
                            <select name="language" class="form-control" id="language_select">
                                <?php foreach (AVAILABLE_LANGUAGES as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $currentLang === $code ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><?php echo Language::get('next'); ?></button>
                    </form>

                <?php elseif ($step === 2): ?>
                    <form method="post">
                        <div class="form-group">
                            <div class="input-group">
                                <input type="text" name="host" class="form-control" placeholder="<?php echo Language::get('host'); ?>" value="<?php echo htmlspecialchars($formData['host']); ?>" required>
                                <div class="input-group-append">
                                    <div class="input-group-text"><span class="fas fa-server"></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-group">
                                <input type="text" name="dbuser" class="form-control" placeholder="<?php echo Language::get('username'); ?>" value="<?php echo htmlspecialchars($formData['dbuser']); ?>" required>
                                <div class="input-group-append">
                                    <div class="input-group-text"><span class="fas fa-user"></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-group">
                                <input type="password" name="dbpass" class="form-control" placeholder="<?php echo Language::get('password'); ?>" value="<?php echo htmlspecialchars($formData['dbpass']); ?>">
                                <div class="input-group-append">
                                    <div class="input-group-text"><span class="fas fa-lock"></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-group">
                                <input type="text" name="dbname" class="form-control" placeholder="<?php echo Language::get('database'); ?>" value="<?php echo htmlspecialchars($formData['dbname']); ?>" required>
                                <div class="input-group-append">
                                    <div class="input-group-text"><span class="fas fa-database"></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <button type="submit" name="back" class="btn btn-secondary btn-block">
                                    <i class="fas fa-arrow-left mr-2"></i><?php echo Language::get('back'); ?>
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <?php echo Language::get('next'); ?><i class="fas fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                <?php elseif ($step === 3): ?>
                    <form method="post">
                        <div class="form-group">
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" placeholder="<?php echo Language::get('admin_password_set'); ?>" required>
                                <div class="input-group-append">
                                    <div class="input-group-text"><span class="fas fa-lock"></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-group">
                                <input type="password" name="password_confirm" class="form-control" placeholder="<?php echo Language::get('confirm_password'); ?>" required>
                                <div class="input-group-append">
                                    <div class="input-group-text"><span class="fas fa-lock"></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <button type="submit" name="back" class="btn btn-secondary btn-block">
                                    <i class="fas fa-arrow-left mr-2"></i><?php echo Language::get('back'); ?>
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <?php echo Language::get('complete_installation'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script>
        $(document).ready(function() {
            // Enter tuşunun davranışını düzelt
            $('form').on('keypress', function(e) {
                if (e.which === 13) { // Enter tuşu
                    e.preventDefault();
                    $(this).find('button[type="submit"]').not('[name="back"]').click();
                }
            });

            // Geri butonuna tıklandığında form validasyonunu devre dışı bırak
            $('button[name="back"]').click(function(e) {
                $(this).closest('form').removeAttr('onsubmit');
                $(this).closest('form').find('input, select').removeAttr('required');
            });

            // Dil değiştiğinde anlık güncelle
            $('#language_select').change(function() {
                var selectedLang = $(this).val();
                $.ajax({
                    url: 'ajax_change_language.php',
                    method: 'POST',
                    data: {
                        language: selectedLang
                    },
                    success: function(response) {
                        if (response.success) {
                            // Etiketleri güncelle
                            $('#language_label').text(response.translations.select_language);
                            $('.login-box-msg').text(response.translations.language_selection);
                            $('button[type="submit"]').text(response.translations.next);
                            document.title = 'Runik MySQL Backup - ' + response.translations.installation;
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>