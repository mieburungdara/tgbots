<?php
// This layout file expects $page_title and $content.
$current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// A simple helper function for nav links, specific to this layout
function is_member_nav_active($slug, $current_path) {
    return strpos($current_path, $slug) !== false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['page_title'] ?? 'Member Area') ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f4f6f8; color: #333; }
        .header { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 0 20px; }
        .nav-container { max-width: 1600px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .nav-container h1 { font-size: 1.5em; color: #333; }
        nav { display: flex; gap: 5px; padding: 10px 0; flex-wrap: wrap; }
        nav a { text-decoration: none; color: #007bff; padding: 10px 15px; border-radius: 5px; white-space: nowrap; }
        nav a:hover { background-color: #f0f0f0; }
        nav a.active { font-weight: bold; background-color: #007bff; color: #fff; }
        .container { max-width: 1600px; margin: 20px auto; padding: 20px; }
        .content { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; word-wrap: break-word; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        h1, h2, h3 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        input[type="text"], input[type="number"], input[type="password"], textarea, select { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button, .btn { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        button:hover, .btn:hover { background-color: #0056b3; }
        .btn-edit { background-color: #28a745; }
        .btn-edit:hover { background-color: #218838; }
        .btn-delete { background-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .dashboard-card { background-color: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee; }
        .dashboard-card h3 { margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px; font-size: 1.1em; color: #555; }
        .dashboard-card .stat-number { font-size: 2em; font-weight: bold; margin: 10px 0 0; color: #007bff; }
        .chart-container { grid-column: 1 / -1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); position: relative; height: 400px; }
        .list-table { width: 100%; border-collapse: collapse; }
        .list-table th, .list-table td { text-align: left; padding: 12px 8px; border-bottom: 1px solid #f0f0f0; }
        .list-table th { font-size: 0.9em; color: #666; font-weight: 600; }
        .list-table tr:last-child td { border-bottom: none; }

        .navbar-toggle { display: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; }

        @media (max-width: 768px) {
            .nav-container { flex-wrap: wrap; }
            nav { display: none; flex-direction: column; width: 100%; background-color: #fff; position: absolute; top: 70px; left: 0; box-shadow: 0 4px 2px -2px gray; }
            nav.active { display: flex; }
            nav a { border-bottom: 1px solid #f0f0f0; border-radius: 0; }
            .navbar-toggle { display: block; }
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="nav-container">
            <h1><a href="/member/dashboard" style="text-decoration: none; color: inherit;">Member Area</a></h1>
            <button class="navbar-toggle" id="navbar-toggle-btn">☰</button>
            <nav id="member-nav">
                <a href="/member/dashboard" class="<?= is_member_nav_active('member/dashboard', $current_path) ? 'active' : '' ?>">Dashboard</a>
                <a href="/member/my_content" class="<?= is_member_nav_active('member/my_content', $current_path) ? 'active' : '' ?>">Konten Saya</a>
                <a href="/member/channels" class="<?= is_member_nav_active('member/channels', $current_path) ? 'active' : '' ?>">Channel Saya</a>
                <a href="/member/purchased" class="<?= is_member_nav_active('member/purchased', $current_path) ? 'active' : '' ?>">Dibeli</a>
                <a href="/member/sold" class="<?= is_member_nav_active('member/sold', $current_path) ? 'active' : '' ?>">Dijual</a>
                <a href="/member/logout">Logout</a>
            </nav>
        </div>
    </header>
    <main class="container">
        <div class="content">
            <?= $content ?? '' ?>
        </div>
    </main>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('navbar-toggle-btn');
        const nav = document.getElementById('member-nav');

        toggleBtn.addEventListener('click', function() {
            nav.classList.toggle('active');
        });
    });
</script>
</body>
</html>
