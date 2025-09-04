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

// DEPRECATED: User management is now in /xoradmin
// $router->get('admin/users', 'Admin/UserController@index');

// DEPRECATED: Package management is now in /xoradmin
// $router->get('admin/packages', 'Admin/MediaPackageController@index');
// $router->post('admin/packages/delete', 'Admin/MediaPackageController@hardDelete');

// Chat management
$router->get('admin/chat', 'Admin/ChatController@index');
$router->get('admin/channel_chat', 'Admin/ChatController@channel');
$router->post('admin/chat/reply', 'Admin/ChatController@reply');
$router->post('admin/chat/delete', 'Admin/ChatController@delete');

// DEPRECATED: Log viewers are now in /xoradmin
// $router->get('admin/logs', 'Admin/LogController@app');
// $router->post('admin/logs/clear', 'Admin/LogController@clearAppLogs');
// $router->get('admin/media_logs', 'Admin/LogController@media');
// $router->get('admin/telegram_logs', 'Admin/LogController@telegram');

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

// DEPRECATED: Balance Page is now in /xoradmin
// $router->get('admin/balance', 'Admin/BalanceController@index');
// $router->post('admin/balance/adjust', 'Admin/BalanceController@adjust');
// $router->get('api/admin/balance/log', 'Admin/BalanceController@getBalanceLog');
// $router->get('api/admin/balance/sales', 'Admin/BalanceController@getSalesLog');
// $router->get('api/admin/balance/purchases', 'Admin/BalanceController@getPurchasesLog');

// DEPRECATED: Analytics is now in /xoradmin
// $router->get('admin/analytics', 'Admin/AnalyticsController@index');

// DEPRECATED: Storage Channels are now in /xoradmin
// $router->get('admin/storage_channels', 'Admin/StorageChannelController@index');
// $router->post('admin/storage_channels/store', 'Admin/StorageChannelController@store');
// $router->post('admin/storage_channels/update', 'Admin/StorageChannelController@update');
// $router->post('admin/storage_channels/delete', 'Admin/StorageChannelController@destroy');
// $router->post('admin/storage_channels/set_default', 'Admin/StorageChannelController@setDefault');

// DEPRECATED: API for Storage Channels is now in /xoradmin
// $router->get('api/admin/storage_channels/bots', 'Admin/StorageChannelController@getBots');
// $router->post('api/admin/storage_channels/bots/add', 'Admin/StorageChannelController@addBot');
// $router->post('api/admin/storage_channels/bots/remove', 'Admin/StorageChannelController@removeBot');
// $router->post('api/admin/storage_channels/bots/verify', 'Admin/StorageChannelController@verifyBot');

// DEPRECATED: Role management is now in /xoradmin
// $router->get('admin/roles', 'Admin/RoleController@index');
// $router->post('admin/roles/store', 'Admin/RoleController@store');
// $router->post('admin/roles/delete', 'Admin/RoleController@destroy');

// DEPRECATED: Database management is now in /xoradmin
// $router->get('admin/database', 'Admin/DatabaseController@index');
// $router->get('admin/database/check', 'Admin/DatabaseController@checkSchema');
// $router->post('admin/database/reset', 'Admin/DatabaseController@reset');
// $router->post('api/admin/database/migrate', 'Admin/DatabaseController@migrate');

// DEPRECATED: Feature Channels are now in /xoradmin
// $router->get('admin/feature-channels', 'Admin/FeatureChannelController@index');
// $router->get('admin/feature-channels/create', 'Admin/FeatureChannelController@create');
// $router->post('admin/feature-channels/store', 'Admin/FeatureChannelController@store');
// $router->get('admin/feature-channels/edit', 'Admin/FeatureChannelController@edit');
// $router->post('admin/feature-channels/update', 'Admin/FeatureChannelController@update');
// $router->post('admin/feature-channels/destroy', 'Admin/FeatureChannelController@destroy');

// Admin - Sales Channels (DEPRECATED - now managed by Feature Channels)
// $router->get('admin/sales_channels', 'Admin/SalesChannelController@index');

// Admin - API Tester
$router->get('xoradmin/api_test_page', 'Admin/ApiTestController@index');
$router->get('api/xoradmin/api_test', 'Admin/ApiTestController@handle');
$router->post('api/xoradmin/api_test', 'Admin/ApiTestController@handle');

// DEPRECATED: Debug Feed is now in /xoradmin
// $router->get('admin/debug_feed', 'Admin/DebugFeedController@index');

// DEPRECATED: Forward Manager is now in /xoradmin
// $router->post('api/admin/media/forward', 'Admin/ForwardManagerController@forward');

// XOR Admin Panel
$router->get('xoradmin', 'Admin/XorAdminController@index');
$router->post('xoradmin/add_bot', 'Admin/XorAdminController@addBot');
$router->post('xoradmin/save_bot_settings', 'Admin/XorAdminController@saveBotSettings');
$router->post('xoradmin/reset_db', 'Admin/XorAdminController@resetDb');
$router->post('xoradmin/hardDeletePackage', 'Admin/XorAdminController@hardDeletePackage');
$router->post('xoradmin/storeStorageChannel', 'Admin/XorAdminController@storeStorageChannel');
$router->post('xoradmin/updateStorageChannel', 'Admin/XorAdminController@updateStorageChannel');
$router->post('xoradmin/destroyStorageChannel', 'Admin/XorAdminController@destroyStorageChannel');
$router->post('xoradmin/storeFeatureChannel', 'Admin/XorAdminController@storeFeatureChannel');
$router->post('xoradmin/updateFeatureChannel', 'Admin/XorAdminController@updateFeatureChannel');
$router->post('xoradmin/destroyFeatureChannel', 'Admin/XorAdminController@destroyFeatureChannel');
$router->get('xoradmin/createFeatureChannel', 'Admin/XorAdminController@createFeatureChannel');
$router->get('xoradmin/editFeatureChannel', 'Admin/XorAdminController@editFeatureChannel');
$router->post('api/xoradmin', 'Admin/XorAdminController@api');
$router->post('api/xoradmin/migrate', 'Admin/XorAdminController@migrate');

// Webhook
$router->post('webhook/{id}', 'WebhookController@handle');

// Add more routes here as the refactoring progresses.
// e.g., $router->get('admin/users', 'Admin/UsersController@index');
