<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// Ambil semua bot untuk dropdown
$bots = $pdo->query("SELECT id, first_name, token FROM bots ORDER BY first_name")->fetchAll();

$selected_bot_token = null;
$api_response = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bot_token'])) {
    $selected_bot_token = $_POST['bot_token'];
    $method = $_POST['method'] ?? '';
    $params = $_POST['params'] ?? [];

    if ($selected_bot_token && $method) {
        try {
            $telegram_api = new TelegramAPI($selected_bot_token, $pdo);

            // Bersihkan parameter kosong
            $params = array_filter($params, function($value) {
                return $value !== '' && $value !== null;
            });

            if (method_exists($telegram_api, $method)) {
                // Panggil metode secara dinamis
                $api_response = call_user_func_array([$telegram_api, $method], $params);
            } else {
                $error = "Metode '{$method}' tidak ada di kelas TelegramAPI.";
            }
        } catch (Exception $e) {
            $error = "Exception: " . $e->getMessage();
            $api_response = ['error' => $error];
        }
    } else {
        $error = "Silakan pilih bot dan metode.";
    }
}

$page_title = 'Tes API Langsung';
include __DIR__ . '/partials/header.php';
?>

<h1>Tes API Langsung untuk Metode Bot</h1>
<p>Halaman ini memungkinkan Anda untuk menguji berbagai metode API Telegram secara langsung.</p>

<form action="api_test.php" method="post" id="api-selector-form">
    <div style="margin-bottom: 15px;">
        <label for="bot_token"><strong>Pilih Bot untuk Diuji:</strong></label>
        <select name="bot_token" id="bot_token" onchange="this.form.submit()">
            <option value="">-- Pilih Bot --</option>
            <?php foreach ($bots as $bot): ?>
                <option value="<?= htmlspecialchars($bot['token']) ?>" <?= ($selected_bot_token === $bot['token']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($bot['first_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($selected_bot_token): ?>
    <hr>
    <h2>Pilih Metode API</h2>
    <?php
    $telegram_api_reflection = new ReflectionClass('TelegramAPI');
    $public_methods = $telegram_api_reflection->getMethods(ReflectionMethod::IS_PUBLIC);

    // Metode yang tidak ingin ditampilkan (misalnya, konstruktor, metode internal)
    $excluded_methods = ['__construct', '__call', 'escapeMarkdown', 'getBotId', 'sendLongMessage'];

    foreach ($public_methods as $method) {
        if (in_array($method->getName(), $excluded_methods) || strpos($method->getName(), 'handle') === 0 || strpos($method->getName(), 'apiRequest') === 0) {
            continue;
        }
    ?>
        <div class="api-method-form">
            <h3><?= $method->getName() ?></h3>
            <form action="api_test.php" method="post">
                <input type="hidden" name="bot_token" value="<?= htmlspecialchars($selected_bot_token) ?>">
                <input type="hidden" name="method" value="<?= $method->getName() ?>">

                <?php
                $parameters = $method->getParameters();
                if (empty($parameters)) {
                    echo "<p>Metode ini tidak memerlukan parameter.</p>";
                } else {
                    foreach ($parameters as $param) {
                ?>
                    <div style="margin-bottom: 10px;">
                        <label for="param-<?= $method->getName() ?>-<?= $param->getName() ?>">
                            <?= $param->getName() ?>
                            <?= $param->isOptional() ? '<em>(opsional)</em>' : '<strong style="color:red;">*</strong>' ?>
                        </label>
                        <br>
                        <?php if (strlen($param->getName()) > 10 && (stripos($param->getName(), 'json') !== false || stripos($param->getName(), 'markup') !== false || stripos($param->getName(), 'results') !== false || stripos($param->getName(), 'media') !== false)): ?>
                            <textarea
                                name="params[<?= $param->getName() ?>]"
                                id="param-<?= $method->getName() ?>-<?= $param->getName() ?>"
                                rows="3"
                                placeholder="<?= htmlspecialchars($param->getName()) ?> (JSON-encoded)"></textarea>
                        <?php else: ?>
                            <input
                                type="text"
                                name="params[<?= $param->getName() ?>]"
                                id="param-<?= $method->getName() ?>-<?= $param->getName() ?>"
                                placeholder="<?= htmlspecialchars($param->getName()) ?>">
                        <?php endif; ?>
                    </div>
                <?php
                    }
                }
                ?>
                <button type="submit">Jalankan <?= $method->getName() ?></button>
            </form>
        </div>
        <hr>
    <?php } ?>
<?php endif; ?>

<?php if ($api_response !== null || $error !== null): ?>
    <h2>Hasil API</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <pre style="background-color: #eee; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word;"><?= htmlspecialchars(json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
<?php endif; ?>

<style>
    .api-method-form {
        margin-bottom: 20px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    .api-method-form h3 {
        margin-top: 0;
    }
    textarea {
        width: calc(100% - 22px);
        padding: 10px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: monospace;
    }
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
