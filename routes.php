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
$router->get('member/logout', 'Auth/LoginController@logout');

// User management
$router->get('admin/users', 'Admin/UserController@index');

// Add more routes here as the refactoring progresses.
// e.g., $router->get('admin/users', 'Admin/UsersController@index');
