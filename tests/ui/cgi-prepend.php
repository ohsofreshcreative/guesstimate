<?php
// Tylko test: kopia bazy na porcie prywatnym, zablokowane HTTP i cron, zalogowany administrator.
define('DB_HOST', '127.0.0.1:33177');
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
$GLOBALS['wp_filter']['determine_current_user'][99][] = ['function' => fn () => (int) ($_SERVER['HTTP_X_TEST_USER'] ?? 1), 'accepted_args' => 1];
$GLOBALS['wp_filter']['option_active_plugins'][99][] = ['function' => fn () => [], 'accepted_args' => 1];
// Przesłane pliki idą do katalogu testowego: prawdziwe uploads zostają nietknięte. Bez tej zmiennej przesyłanie jest wyłączone.
$GLOBALS['wp_filter']['upload_dir'][99][] = ['function' => function ($dirs) {
	$root = getenv('GST_TEST_UPLOADS');

	if (! $root) {
		return array_merge($dirs, ['error' => 'Przesyłanie w testach wymaga GST_TEST_UPLOADS.']);
	}

	return array_merge($dirs, ['basedir' => $root, 'path' => $root . $dirs['subdir'], 'baseurl' => 'http://guesstimate.local/gst-test-uploads', 'url' => 'http://guesstimate.local/gst-test-uploads' . $dirs['subdir']]);
}, 'accepted_args' => 1];
$GLOBALS['wp_filter']['after_setup_theme'][1][] = ['function' => function () {
	if (function_exists('config') && ! function_exists('get_field')) { eval('function get_field() { return null; }'); }
}, 'accepted_args' => 0];
