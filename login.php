<?php
session_start();
require_once 'app/config/config.php';
require_once 'app/models/Language.php';

// Dil değişikliği yapıldıysa
if (isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Dil ayarını yükle
$currentLang = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
Language::init($currentLang);

if (!file_exists('app/config/installed.php')) {
    header('Location: install/index.php');
    exit;
}

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['language'])) {
    if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = Language::get('invalid_password');
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <title>Runik MySQL Backup - <?php echo Language::get('login'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .login-box {
            margin-top: 10vh;
        }
        [lang="ar"] {
            direction: rtl;
        }
        .language-select {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            width: 200px;
        }
        [lang="ar"] .language-select {
            left: 20px;
            right: auto;
        }
    </style>
</head>
<body class="hold-transition login-page">
    <!-- Dil Seçimi -->
    <div class="language-select">
        <form method="post" id="languageForm">
            <div class="form-group">
                <div class="input-group">
                    <select name="language" class="form-control" id="language_select">
                        <?php foreach(AVAILABLE_LANGUAGES as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $currentLang === $code ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <i class="fas fa-globe"></i>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h1><b>Runik</b> MySQL Backup</h1>
            </div>
            <div class="card-body">
                <p class="login-box-msg"><?php echo Language::get('login_message'); ?></p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form action="app/ajax/login.php" method="post" id="loginForm">
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" name="password" placeholder="<?php echo Language::get('password'); ?>" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">
                                <?php echo Language::get('login_button'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script>
    $(document).ready(function() {
        // Dil değiştiğinde otomatik submit
        $('#language_select').change(function() {
            $('#languageForm').submit();
        });

        // Login form submit
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        window.location.href = 'index.php';
                    } else {
                        alert(response.error || '<?php echo Language::get("invalid_password"); ?>');
                    }
                },
                error: function() {
                    alert('<?php echo Language::get("system_error"); ?>');
                }
            });
        });
    });
    </script>
</body>
</html> 