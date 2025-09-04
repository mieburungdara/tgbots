<?php

// Define application routes here.
// The router instance is available as $router within the load() method context.

// Home page / root route
$router->get('', 'HomeController@index');

// Auth routes
$router->get('login', 'Auth/LoginController@handleToken');
$router->get('logout', 'Auth/LoginController@logout');

// DEPRECATED: Admin routes are being merged into /xoradmin
// $router->get('admin', 'Admin/DashboardController@index');
// $router->get('admin/dashboard', 'Admin/DashboardController@index');

// DEPRECATED: Bot management is now in /xoradmin
// $router->get('admin/bots', 'Admin/BotController@index');
// $router->post('admin/bots', 'Admin/BotController@store');
// $router->get('admin/bots/edit', 'Admin/BotController@edit');
// $router->post('admin/bots/settings', 'Admin/BotController@updateSettings');

// Member area routes
$router->get('member/login', 'Member/LoginController@showLoginForm');
$router->get('member/token-login', 'Member/LoginController@processLinkLogin'); // For links from bot
$router->post('member/login', 'Member/LoginController@processFormLogin');    // For form submission
$router->get('member/dashboard', 'Member/DashboardController@index');
$router->get('member/my_content', 'Member/ContentController@index');
$router->get('member/content/edit', 'Member/ContentController@edit');
$router->post('member/content/update', 'Member/ContentController@update');
// $router->get('member/channels', 'Member/ChannelController@index'); // DEPRECATED
// $router->post('member/channels', 'Member/ChannelController@register'); // DEPRECATED
$router->get('member/purchased', 'Member/TransactionController@purchased');
$router->get('member/sold', 'Member/TransactionController@sold');
$router->post('member/transactions/delete_package', 'Member/TransactionController@softDeletePackage');
$router->get('member/content/show', 'Member/ContentController@show');
$router->get('member/logout', 'Auth/LoginController@logout');

// User management
$router->get('admin/users', 'Admin/UserController@index');

// Package management
$router->get('admin/packages', 'Admin/MediaPackageController@index');
$router->post('admin/packages/delete', 'Admin/MediaPackageController@hardDelete');

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
// DEPRECATED: Role management is now in /xoradmin
// $router->get('api/admin/user/roles', 'Admin/UserController@getRoles');
// $router->post('api/admin/user/roles', 'Admin/UserController@updateRoles');
$router->post('api/member/content/toggle-protection', 'Member/ContentController@toggleProtection');

// DEPRECATED: Bot Management API is now in /xoradmin
// $router->post('api/admin/bots/set-webhook', 'Admin/BotController@setWebhook');
// $router->post('api/admin/bots/check-webhook', 'Admin/BotController@getWebhookInfo');
// $router->post('api/admin/bots/delete-webhook', 'Admin/BotController@deleteWebhook');
// $router->post('api/admin/bots/get-me', 'Admin/BotController@getMe');
// $router->post('api/admin/bots/test-webhook', 'Admin/BotController@testWebhook');

// Balance Page
$router->get('admin/balance', 'Admin/BalanceController@index');
$router->post('admin/balance/adjust', 'Admin/BalanceController@adjust');
$router->get('api/admin/balance/log', 'Admin/BalanceController@getBalanceLog');
$router->get('api/admin/balance/sales', 'Admin/BalanceController@getSalesLog');
$router->get('api/admin/balance/purchases', 'Admin/BalanceController@getPurchasesLog');

// Analytics
$router->get('admin/analytics', 'Admin/AnalyticsController@index');

// Admin - Storage Channels
$router->get('admin/storage_channels', 'Admin/StorageChannelController@index');
$router->post('admin/storage_channels/store', 'Admin/StorageChannelController@store');
$router->post('admin/storage_channels/update', 'Admin/StorageChannelController@update');
$router->post('admin/storage_channels/delete', 'Admin/StorageChannelController@destroy');
$router->post('admin/storage_channels/set_default', 'Admin/StorageChannelController@setDefault');

// API for Storage Channels
$router->get('api/admin/storage_channels/bots', 'Admin/StorageChannelController@getBots');
$router->post('api/admin/storage_channels/bots/add', 'Admin/StorageChannelController@addBot');
$router->post('api/admin/storage_channels/bots/remove', 'Admin/StorageChannelController@removeBot');
$router->post('api/admin/storage_channels/bots/verify', 'Admin/StorageChannelController@verifyBot');

// DEPRECATED: Role management is now in /xoradmin
// $router->get('admin/roles', 'Admin/RoleController@index');
// $router->post('admin/roles/store', 'Admin/RoleController@store');
// $router->post('admin/roles/delete', 'Admin/RoleController@destroy');

// DEPRECATED: Database management is now in /xoradmin
// $router->get('admin/database', 'Admin/DatabaseController@index');
// $router->get('admin/database/check', 'Admin/DatabaseController@checkSchema');
// $router->post('admin/database/reset', 'Admin/DatabaseController@reset');
// $router->post('api/admin/database/migrate', 'Admin/DatabaseController@migrate');

// Admin - Feature Channels
$router->get('admin/feature-channels', 'Admin/FeatureChannelController@index');
$router->get('admin/feature-channels/create', 'Admin/FeatureChannelController@create');
$router->post('admin/feature-channels/store', 'Admin/FeatureChannelController@store');
$router->get('admin/feature-channels/edit', 'Admin/FeatureChannelController@edit');
$router->post('admin/feature-channels/update', 'Admin/FeatureChannelController@update');
$router->post('admin/feature-channels/destroy', 'Admin/FeatureChannelController@destroy');

// Admin - Sales Channels (DEPRECATED - now managed by Feature Channels)
// $router->get('admin/sales_channels', 'Admin/SalesChannelController@index');

// Admin - API Tester
$router->get('admin/api_test', 'Admin/ApiTestController@index');
$router->get('api/admin/api_test', 'Admin/ApiTestController@handle');
$router->post('api/admin/api_test', 'Admin/ApiTestController@handle');

// Admin - Debug Feed
$router->get('admin/debug_feed', 'Admin/DebugFeedController@index');

// Admin - Forward Manager
$router->post('api/admin/media/forward', 'Admin/ForwardManagerController@forward');

// XOR Admin Panel
$router->get('xoradmin', 'Admin/XorAdminController@index');
$router->post('xoradmin/add_bot', 'Admin/XorAdminController@addBot');
$router->post('xoradmin/save_bot_settings', 'Admin/XorAdminController@saveBotSettings');
$router->post('xoradmin/reset_db', 'Admin/XorAdminController@resetDb');
$router->post('api/xoradmin', 'Admin/XorAdminController@api');
$router->post('api/xoradmin/migrate', 'Admin/XorAdminController@migrate');

// Webhook
$router->post('webhook/{id}', 'WebhookController@handle');

// Add more routes here as the refactoring progresses.
// e.g., $router->get('admin/users', 'Admin/UsersController@index');
