<?php

// Define application routes here.
// The router instance is available as $router within the load() method context.

// Auth routes
$router->get('login', 'Auth/LoginController@handleToken');
$router->get('logout', 'Auth/LoginController@logout');

// Route for the admin dashboard
// This will handle requests for /admin and /admin/dashboard
$router->get('admin', 'Admin/DashboardController@index');
$router->get('admin/dashboard', 'Admin/DashboardController@index');

// Bot management routes
$router->get('admin/bots', 'Admin/BotController@index');
$router->post('admin/bots', 'Admin/BotController@store');
$router->get('admin/bots/edit', 'Admin/BotController@edit');
$router->post('admin/bots/settings', 'Admin/BotController@updateSettings');

// Member area routes
$router->get('member/login', 'Member/LoginController@showLoginForm');
$router->get('member/token-login', 'Member/LoginController@processLinkLogin'); // For links from bot
$router->post('member/login', 'Member/LoginController@processFormLogin');    // For form submission
$router->get('member/dashboard', 'Member/DashboardController@index');
$router->get('member/my_content', 'Member/ContentController@index');
$router->get('member/content/edit', 'Member/ContentController@edit');
$router->post('member/content/update', 'Member/ContentController@update');
$router->get('member/channels', 'Member/ChannelController@index');
$router->post('member/channels', 'Member/ChannelController@register');
$router->get('member/purchased', 'Member/TransactionController@purchased');
$router->get('member/sold', 'Member/TransactionController@sold');
$router->post('member/transactions/delete_package', 'Member/TransactionController@softDeletePackage');
$router->get('member/content/show', 'Member/ContentController@show');
$router->get('member/logout', 'Auth/LoginController@logout');

// User management
$router->get('admin/users', 'Admin/UserController@index');

// Package management
$router->get('admin/packages', 'Admin/PackageController@index');
$router->post('admin/packages/delete', 'Admin/PackageController@hardDelete');

// Chat management
$router->get('admin/chat', 'Admin/ChatController@index');
$router->get('admin/channel_chat', 'Admin/ChatController@channel');
$router->post('admin/chat/reply', 'Admin/ChatController@reply');
$router->post('admin/chat/delete', 'Admin/ChatController@delete');

// Log viewers
$router->get('admin/logs', 'Admin/LogController@app');
$router->post('admin/logs/clear', 'Admin/LogController@clearAppLogs');
$router->get('admin/media_logs', 'Admin/LogController@media');
$router->get('admin/telegram_logs', 'Admin/LogController@telegram');

// API routes
$router->get('api/admin/user/roles', 'Admin/UserController@getRoles');
$router->post('api/admin/user/roles', 'Admin/UserController@updateRoles');
$router->post('api/member/content/toggle-protection', 'Member/ContentController@toggleProtection');

// Bot Management API
$router->post('api/admin/bots/set-webhook', 'Admin/BotController@setWebhook');
$router->post('api/admin/bots/check-webhook', 'Admin/BotController@getWebhookInfo');
$router->post('api/admin/bots/delete-webhook', 'Admin/BotController@deleteWebhook');
$router->post('api/admin/bots/get-me', 'Admin/BotController@getMe');
$router->post('api/admin/bots/test-webhook', 'Admin/BotController@testWebhook');

// Balance Page
$router->get('admin/balance', 'Admin/BalanceController@index');
$router->post('admin/balance/adjust', 'Admin/BalanceController@adjust');
$router->get('api/admin/balance/log', 'Admin/BalanceController@getBalanceLog');
$router->get('api/admin/balance/sales', 'Admin/BalanceController@getSalesLog');
$router->get('api/admin/balance/purchases', 'Admin/BalanceController@getPurchasesLog');

// Analytics
$router->get('admin/analytics', 'Admin/AnalyticsController@index');

// Add more routes here as the refactoring progresses.
// e.g., $router->get('admin/users', 'Admin/UsersController@index');
