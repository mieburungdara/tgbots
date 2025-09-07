<?php

// Define application routes here.
// The router instance is available as $router within the load() method context.

// Home page / root route
$router->get('', 'HomeController@index');

// --- Admin Panel Routes ---
// Auth
$router->get('xoradmin/login', 'Admin/LoginController@showLoginForm');
$router->post('xoradmin/login', 'Admin/LoginController@processLogin');
$router->get('xoradmin/logout', 'Admin/LoginController@logout');

// Dashboard
$router->get('xoradmin', 'Admin/DashboardController@index');
$router->get('xoradmin/dashboard', 'Admin/DashboardController@index');

// Bot management
$router->get('xoradmin/bots', 'Admin/BotController@index');
$router->post('xoradmin/bots', 'Admin/BotController@store');
$router->get('xoradmin/bots/edit', 'Admin/BotController@edit');
$router->post('xoradmin/bots/settings', 'Admin/BotController@updateSettings');

// User management
$router->get('xoradmin/users', 'Admin/UserController@index');

// Package management
$router->get('xoradmin/packages', 'Admin/MediaPackageController@index');
$router->post('xoradmin/packages/delete', 'Admin/MediaPackageController@hardDelete');

// Chat management
$router->get('xoradmin/chat', 'Admin/ChatController@index');
$router->get('xoradmin/channel_chat', 'Admin/ChatController@channel');
$router->post('xoradmin/chat/reply', 'Admin/ChatController@reply');
$router->post('xoradmin/chat/delete', 'Admin/ChatController@delete');

// Log viewers
$router->get('xoradmin/logs', 'Admin/LogController@app');
$router->post('xoradmin/logs/clear', 'Admin/LogController@clearAppLogs');
$router->get('xoradmin/media_logs', 'Admin/LogController@media');
$router->get('xoradmin/public_error_log', 'Admin/LogController@publicErrorLog');
$router->post('xoradmin/public_error_log/clear', 'Admin/LogController@clearPublicErrorLog');
$router->get('xoradmin/telegram_logs', 'Admin/LogController@telegram');

// API routes for Admin
$router->post('api/xoradmin/user/roles', 'Admin/UserController@updateRoles');
$router->get('api/xoradmin/user/roles', 'Admin/UserController@getRoles');
$router->get('api/xoradmin/balance/log', 'Admin/BalanceController@getBalanceLog');
$router->get('api/xoradmin/balance/sales', 'Admin/BalanceController@getSalesLog');
$router->get('api/xoradmin/balance/purchases', 'Admin/BalanceController@getPurchasesLog');
$router->get('api/xoradmin/storage_channels/bots', 'Admin/StorageChannelController@getBots');
$router->post('api/xoradmin/storage_channels/bots/add', 'Admin/StorageChannelController@addBot');
$router->post('api/xoradmin/storage_channels/bots/remove', 'Admin/StorageChannelController@removeBot');
$router->post('api/xoradmin/storage_channels/bots/verify', 'Admin/StorageChannelController@verifyBot');
$router->post('api/xoradmin/database/migrate', 'Admin/DatabaseController@migrate');
$router->get('api/xoradmin/api_test', 'Admin/ApiTestController@handle');
$router->post('api/xoradmin/api_test', 'Admin/ApiTestController@handle');
$router->post('api/xoradmin/media/forward', 'Admin/ForwardManagerController@forward');
$router->post('api/xoradmin/bots/check-webhook', 'Admin/BotController@getWebhookInfo');
$router->post('api/xoradmin/bots/get-me', 'Admin/BotController@getMe');

// Balance Page
$router->get('xoradmin/balance', 'Admin/BalanceController@index');
$router->post('xoradmin/balance/adjust', 'Admin/BalanceController@adjust');

// Analytics
$router->get('xoradmin/analytics', 'Admin/AnalyticsController@index');

// Storage Channels
$router->get('xoradmin/storage_channels', 'Admin/StorageChannelController@index');
$router->post('xoradmin/storage_channels/store', 'Admin/StorageChannelController@store');
$router->post('xoradmin/storage_channels/update', 'Admin/StorageChannelController@update');
$router->post('xoradmin/storage_channels/delete', 'Admin/StorageChannelController@destroy');

// Roles
$router->get('xoradmin/roles', 'Admin/RoleController@index');
$router->post('xoradmin/roles/store', 'Admin/RoleController@store');
$router->post('xoradmin/roles/delete', 'Admin/RoleController@destroy');

// Database
$router->get('xoradmin/database', 'Admin/DatabaseController@index');
$router->get('xoradmin/database/check', 'Admin/DatabaseController@checkSchema');
$router->post('xoradmin/database/reset', 'Admin/DatabaseController@reset');

// Feature Channels
$router->get('xoradmin/feature-channels', 'Admin/FeatureChannelController@index');
$router->get('xoradmin/feature-channels/create', 'Admin/FeatureChannelController@create');
$router->post('xoradmin/feature-channels/store', 'Admin/FeatureChannelController@store');
$router->get('xoradmin/feature-channels/edit', 'Admin/FeatureChannelController@edit');
$router->post('xoradmin/feature-channels/update', 'Admin/FeatureChannelController@update');
$router->post('xoradmin/feature-channels/destroy', 'Admin/FeatureChannelController@destroy');

// API Tester
$router->get('xoradmin/api_test', 'Admin/ApiTestController@index');

// Debug Feed
$router->get('xoradmin/debug_feed', 'Admin/DebugFeedController@index');


// --- Member Area Routes ---
$router->get('member/login', 'Member/LoginController@showLoginForm');
$router->get('member/token-login', 'Member/LoginController@processLinkLogin');
$router->post('member/login', 'Member/LoginController@processFormLogin');
$router->get('member/dashboard', 'Member/DashboardController@index');
$router->get('member/channels', 'Member/ContentController@channels');
$router->post('member/channels/register', 'Member/ContentController@registerChannel');
$router->post('member/channels/delete', 'Member/ContentController@deleteChannel');
$router->get('member/my_content', 'Member/ContentController@index');
$router->get('member/content/edit', 'Member/ContentController@edit');
$router->post('member/content/update', 'Member/ContentController@update');
$router->get('member/purchased', 'Member/TransactionController@purchased');
$router->get('member/sold', 'Member/TransactionController@sold');
$router->post('member/transactions/delete_package', 'Member/TransactionController@softDeletePackage');
$router->get('member/content/show', 'Member/ContentController@show');
$router->get('member/logout', 'Admin/LoginController@logout'); // Point to the new generic logout
$router->post('api/member/content/toggle-protection', 'Member/ContentController@toggleProtection');


// --- Webhook ---
$router->post('webhook/{id}', 'WebhookController@handle');
