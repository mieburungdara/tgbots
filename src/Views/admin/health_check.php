<?php
// This view assumes all data variables are available in the $data array.
?>

<style>
    .health-check-output {
        background-color: #1e293b; /* cool-gray-800 */
        color: #e2e8f0; /* cool-gray-300 */
        padding: 1.5rem;
        border-radius: 0.5rem;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
        font-size: 0.875rem;
        line-height: 1.5;
        white-space: pre-wrap;
        word-wrap: break-word;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .btn-refresh {
        display: inline-block;
        margin-bottom: 1rem;
        padding: 0.75rem 1.5rem;
        background-color: #4f46e5; /* indigo-600 */
        color: white;
        font-weight: 600;
        border-radius: 0.375rem;
        text-decoration: none;
        transition: background-color 0.2s ease-in-out;
    }
    .btn-refresh:hover {
        background-color: #4338ca; /* indigo-700 */
    }
</style>

<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">{htmlspecialchars($data['page_title'])}</h1>
        <a href="/xoradmin/health_check" class="btn-refresh">Jalankan Ulang Pemeriksaan</a>
    </div>

    <p class="mb-4 text-gray-600">Ini adalah hasil dari skrip <code>doctor.php</code> yang dijalankan di server. Gunakan ini untuk mendiagnosis masalah konfigurasi atau lingkungan.</p>

    <div class="health-check-output">
        <pre><?= $data['check_output'] ?></pre>
    </div>
</div>
