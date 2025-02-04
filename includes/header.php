<?php
session_start();

// Dil değişikliği yapıldıysa
if (isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];
    // Mevcut sayfaya yönlendir
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/models/Database.php';
require_once __DIR__ . '/../app/models/Language.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Dil ayarını yükle (session'da varsa onu kullan, yoksa varsayılanı kullan)
$currentLang = $_SESSION['language'] ?? DEFAULT_LANGUAGE;
Language::init($currentLang);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Runik MySQL Backup</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    $(document).ready(function() {
        // Çıkış yap
        $('#logout').click(function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: '<?php echo Language::get("logout"); ?>',
                text: '<?php echo Language::get("logout_confirm"); ?>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<?php echo Language::get("yes"); ?>',
                cancelButtonText: '<?php echo Language::get("cancel"); ?>',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'app/ajax/logout.php',
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                window.location.href = 'login.php';
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: '<?php echo Language::get("error"); ?>',
                                text: '<?php echo Language::get("system_error"); ?>',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        });

        // Fabrika ayarlarına dön
        $('#resetSystem').click(function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: '<?php echo Language::get("factory_reset"); ?>',
                text: '<?php echo Language::get("factory_reset_confirm"); ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<?php echo Language::get("yes"); ?>',
                cancelButtonText: '<?php echo Language::get("cancel"); ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'app/ajax/reset.php',
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '<?php echo Language::get("success"); ?>',
                                    text: '<?php echo Language::get("reset_success"); ?>',
                                    icon: 'success',
                                    confirmButtonText: '<?php echo Language::get("ok"); ?>'
                                }).then(() => {
                                    window.location.href = 'install/index.php';
                                });
                            } else {
                                Swal.fire({
                                    title: '<?php echo Language::get("error"); ?>',
                                    text: response.error || '<?php echo Language::get("system_error"); ?>',
                                    icon: 'error'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: '<?php echo Language::get("error"); ?>',
                                text: '<?php echo Language::get("system_error"); ?>',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        });
    });
    </script>
</head>
<body class="hold-transition sidebar-mini"> 