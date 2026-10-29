<?php
/**
 * پاک‌سازی داده‌ها هنگام حذف کامل افزونه از پیشخوان.
 *
 * @package FarsiNumericCaptcha
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// حذف تنظیمات.
delete_option( 'fnc_settings' );

// حذف کدهای باقی‌مانده کپچا (در صورت وجود).
global $wpdb;

$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_fnc_%'
	    OR option_name LIKE '_transient_timeout_fnc_%'"
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
