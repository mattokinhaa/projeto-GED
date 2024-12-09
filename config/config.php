<?php
// Configurações de Ambiente
define('ENVIRONMENT', 'development'); // development, production

// Configurações de Erro
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'ged_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações de E-mail
define('SMTP_HOST', 'smtp.exemplo.com');
define('SMTP_USER', 'seu_email@exemplo.com');
define('SMTP_PASS', 'sua_senha');
define('SMTP_PORT', 587);
define('MAIL_FROM', 'sistema@exemplo.com');
define('MAIL_FROM_NAME', 'Sistema GED');

// Configurações de Upload
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Configurações de Log
define('LOG_PATH', __DIR__ . '/../logs');
define('LOG_FILE', LOG_PATH . '/system.log');

// Configurações de Sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', ENVIRONMENT === 'production');
session_start();

// URLs e Paths
define('BASE_URL', 'http://localhost/ged_system');
define('ASSETS_URL', BASE_URL . '/assets');

// Configurações de Paginação
define('ITEMS_PER_PAGE', 20);

// Configurações de Alerta
define('DIAS_ALERTA_VENCIMENTO', 30);

// Criar diretórios necessários
$directories = [
    LOG_PATH,
    UPLOAD_PATH . '/asos',
    UPLOAD_PATH . '/exames',
    UPLOAD_PATH . '/anexos'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Função de autoload das classes
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Funções Helpers
function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Configurações de Timezone
date_default_timezone_set('America/Sao_Paulo');
?>