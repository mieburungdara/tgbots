<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

// Ambil semua bot untuk dropdown
$bots = $pdo->query("SELECT id, first_name, token FROM bots ORDER BY first_name")->fetchAll();
$selected_bot_id = $_GET['bot_id'] ?? ($bots[0]['id'] ?? null);

$page_title = 'Tes API Langsung';
include __DIR__ . '/partials/header.php';
?>

<div class="api-tester-container">
    <h1>Tes API Langsung untuk Metode Bot</h1>
    <p>Pilih bot, pilih metode, isi parameter, dan jalankan permintaan secara real-time.</p>

    <form id="bot-selector-form" class="form-section">
        <label for="bot_id"><strong>Pilih Bot:</strong></label>
        <select name="bot_id" id="bot_id">
            <?php foreach ($bots as $bot): ?>
                <option value="<?= htmlspecialchars($bot['id']) ?>" <?= ($selected_bot_id == $bot['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($bot['first_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <hr>

    <div id="api-interaction-section">
        <div class="form-section">
            <label for="api-method-selector"><strong>Pilih Metode API:</strong></label>
            <select id="api-method-selector">
                <option value="">-- Memuat metode... --</option>
            </select>
        </div>

        <form id="api-params-form" class="form-section">
            <div id="params-container">
                <!-- Parameter form fields will be injected here by JavaScript -->
            </div>
            <button type_button="submit" id="run-request-btn" style="display: none;">Jalankan Permintaan</button>
        </form>

        <div id="api-result-container" class="result-section" style="display: none;">
            <h3>Hasil API</h3>
            <pre id="api-result"></pre>
        </div>
    </div>

    <hr>

    <div id="history-section" class="history-section">
        <h2>Riwayat Permintaan</h2>
        <div id="history-table-container">
            <!-- History table will be injected here -->
        </div>
        <div id="history-pagination">
            <!-- Pagination controls will be injected here -->
        </div>
    </div>
</div>

<style>
    .api-tester-container { max-width: 100%; }
    .form-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
    .result-section, .history-section { margin-top: 20px; }
    #api-result, #history-table-container { background-color: #eee; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; }
    #history-table-container table { width: 100%; font-size: 14px; }
    #history-table-container table th, #history-table-container table td { white-space: normal; word-break: break-all; }
    .pagination-btn { margin: 0 5px; }
    textarea { width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: monospace; min-height: 80px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const botSelector = document.getElementById('bot_id');
    const methodSelector = document.getElementById('api-method-selector');
    const paramsContainer = document.getElementById('params-container');
    const runRequestBtn = document.getElementById('run-request-btn');
    const apiResultContainer = document.getElementById('api-result-container');
    const apiResultEl = document.getElementById('api-result');
    const apiParamsForm = document.getElementById('api-params-form');

    const historyContainer = document.getElementById('history-table-container');
    const paginationContainer = document.getElementById('history-pagination');

    let methodsData = {};
    let currentPage = 1;

    const HANDLER_URL = 'api_test_handler.php';

    // --- Fungsi Utama ---

    async function fetchMethods() {
        methodSelector.innerHTML = '<option value="">-- Memuat metode... --</option>';
        try {
            const response = await fetch(`${HANDLER_URL}?action=get_methods`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            methodsData = data;

            methodSelector.innerHTML = '<option value="">-- Pilih Metode --</option>';
            for (const methodName in methodsData) {
                const option = new Option(methodName, methodName);
                methodSelector.appendChild(option);
            }
        } catch (e) {
            console.error('Gagal mengambil metode API:', e);
            methodSelector.innerHTML = '<option value="">-- Gagal memuat --</option>';
        }
    }

    function buildParamsForm(container, params, prefix = '') {
        for (const paramName in params) {
            const param = params[paramName];
            const inputId = `param-${prefix}${paramName}`;
            const inputName = prefix ? `${prefix}[${paramName}]` : paramName;

            const div = document.createElement('div');
            div.style.marginBottom = '10px';

            const label = document.createElement('label');
            label.htmlFor = inputId;
            label.innerHTML = `${paramName} ${param.isOptional ? '<em>(opsional)</em>' : '<strong style="color:red;">*</strong>'}`;
            div.appendChild(label);
            div.appendChild(document.createElement('br'));

            let input;
            switch (param.type) {
                case 'dropdown':
                    input = document.createElement('select');
                    input.add(new Option('-- Pilih --', ''));
                    param.choices.forEach(choice => input.add(new Option(choice, choice)));
                    break;
                case 'json':
                    input = document.createElement('textarea');
                    input.rows = 3;
                    input.placeholder = `${paramName} (JSON-encoded)`;
                    break;
                case 'boolean':
                    input = document.createElement('input');
                    input.type = 'checkbox';
                    input.value = '1'; // Send '1' if checked
                    break;
                case 'object':
                    const fieldset = document.createElement('fieldset');
                    fieldset.style.border = '1px solid #ccc';
                    fieldset.style.padding = '10px';
                    const legend = document.createElement('legend');
                    legend.textContent = paramName;
                    fieldset.appendChild(legend);
                    buildParamsForm(fieldset, param.properties, inputName);
                    div.appendChild(fieldset);
                    container.appendChild(div);
                    continue; // Skip appending normal input
                default: // text, number
                    input = document.createElement('input');
                    input.type = param.type === 'number' ? 'number' : 'text';
                    input.placeholder = paramName;
            }

            input.id = inputId;
            input.name = inputName;
            div.appendChild(input);
            container.appendChild(div);
        }
    }

    function serializeForm(form) {
        const formData = new FormData(form);
        const obj = {};
        for (const [key, value] of formData.entries()) {
            // Handle nested keys like "reply_parameters[message_id]"
            const keys = key.match(/[^[\]]+/g);
            if (keys.length > 1) {
                let current = obj;
                keys.forEach((k, i) => {
                    if (i === keys.length - 1) {
                        current[k] = value;
                    } else {
                        current[k] = current[k] || {};
                        current = current[k];
                    }
                });
            } else {
                obj[key] = value;
            }
        }
        return obj;
    }


    async function runApiRequest(event) {
        event.preventDefault();
        const selectedBotId = botSelector.value;
        const selectedMethod = methodSelector.value;
        const params = serializeForm(apiParamsForm);

        apiResultContainer.style.display = 'block';
        apiResultEl.textContent = 'Menjalankan...';

        try {
            const response = await fetch(HANDLER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'run_method',
                    bot_id: selectedBotId,
                    method: selectedMethod,
                    params: params
                })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            let resultString = JSON.stringify(result.api_response, null, 2);
            apiResultEl.textContent = resultString;

            fetchHistory(1); // Refresh history to show the new request
        } catch (e) {
            console.error('Gagal menjalankan permintaan API:', e);
            apiResultEl.textContent = `Error: ${e.message}`;
        }
    }

    async function fetchHistory(page = 1) {
        currentPage = page;
        const selectedBotId = botSelector.value;
        historyContainer.textContent = 'Memuat riwayat...';
        try {
            const response = await fetch(`${HANDLER_URL}?action=get_history&bot_id=${selectedBotId}&page=${page}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            renderHistory(data.history);
            renderPagination(data.pagination);
        } catch (e) {
            console.error('Gagal mengambil riwayat:', e);
            historyContainer.textContent = 'Gagal memuat riwayat.';
        }
    }

    function renderHistory(history) {
        if (history.length === 0) {
            historyContainer.innerHTML = '<p>Belum ada riwayat untuk bot ini.</p>';
            return;
        }
        let tableHtml = `
            <table class="table">
                <thead><tr><th>Waktu</th><th>Metode</th><th>Request</th><th>Response</th></tr></thead>
                <tbody>`;
        for (const item of history) {
            tableHtml += `
                <tr>
                    <td>${item.created_at}</td>
                    <td>${item.method}</td>
                    <td><pre>${JSON.stringify(JSON.parse(item.request_payload), null, 2)}</pre></td>
                    <td><pre>${JSON.stringify(JSON.parse(item.response_payload), null, 2)}</pre></td>
                </tr>`;
        }
        tableHtml += `</tbody></table>`;
        historyContainer.innerHTML = tableHtml;
    }

    function renderPagination(pagination) {
        paginationContainer.innerHTML = '';
        if (pagination.total_pages <= 1) return;

        let paginationHtml = 'Halaman: ';
        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === pagination.current_page) {
                paginationHtml += `<strong>${i}</strong> `;
            } else {
                paginationHtml += `<a href="#" class="pagination-btn" data-page="${i}">${i}</a> `;
            }
        }
        paginationContainer.innerHTML = paginationHtml;
    }


    // --- Event Listeners ---

    botSelector.addEventListener('change', () => fetchHistory(1));

    methodSelector.addEventListener('change', () => {
        const methodName = methodSelector.value;
        paramsContainer.innerHTML = ''; // Hapus formulir sebelumnya
        runRequestBtn.style.display = 'none';

        if (methodName && methodsData[methodName]) {
            buildParamsForm(paramsContainer, methodsData[methodName].parameters);
            runRequestBtn.style.display = 'block';
        }
    });

    apiParamsForm.addEventListener('submit', runApiRequest);

    paginationContainer.addEventListener('click', function(e) {
        if (e.target.matches('.pagination-btn')) {
            e.preventDefault();
            const page = parseInt(e.target.dataset.page, 10);
            fetchHistory(page);
        }
    });

    // --- Inisialisasi ---
    fetchMethods();
    if (botSelector.value) {
        fetchHistory(1);
    }
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
