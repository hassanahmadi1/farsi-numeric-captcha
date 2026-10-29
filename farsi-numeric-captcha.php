<?php
/**
 * Plugin Name:       کپچای عددی فارسی
 * Plugin URI:        https://example.com/farsi-numeric-captcha
 * Description:       کپچای تصویری ساده، خوانا و کاملاً فارسی برای فرم نظرات وردپرس (و در صورت تمایل صفحه ورود، ثبت‌نام و بازیابی رمز). بدون سرویس بیرونی، بدون API و بدون کندی سایت.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.0
 * Author:            Arena
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       farsi-numeric-captcha
 * Domain Path:       /languages
 *
 * @package FarsiNumericCaptcha
 */

defined( 'ABSPATH' ) || exit;

define( 'FNC_VERSION', '1.0.0' );
define( 'FNC_FILE', __FILE__ );
define( 'FNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'FNC_URL', plugin_dir_url( __FILE__ ) );

require_once FNC_DIR . 'includes/class-fnc-captcha.php';
require_once FNC_DIR . 'includes/class-fnc-forms.php';
require_once FNC_DIR . 'includes/class-fnc-settings.php';

/**
 * مقادیر پیش‌فرض تنظیمات افزونه.
 *
 * @return array
 */
function fnc_defaults() {
	return array(
		// نمایش.
		'enable_comments'     => 1,
		'enable_login'        => 1,
		'enable_register'     => 0,
		'enable_lostpassword' => 0,
		'hide_for_logged_in'  => 1,
		// ظاهر.
		'length'              => 4,
		'width'               => 160,
		'height'              => 55,
		'font_size'           => 26,
		'digit_style'         => 'latin', // latin | persian
		'noise'               => 'medium', // light | medium | strong.
		'bg'                  => '#f7f7f7',
		'text_color'          => '#1d2327',
		// متن‌ها و امنیت.
		'label'               => 'کد امنیتی را وارد کنید',
		'placeholder'         => 'مثلاً 3842',
		'max_attempts'        => 6,
		'lockout_minutes'     => 10,
		'expire_minutes'      => 15,
	);
}

/**
 * خواندن یک تنظیم با مقدار پیش‌فرض.
 *
 * @param string $key کلید تنظیم.
 * @return mixed
 */
function fnc_option( $key ) {
	static $settings = null;

	if ( null === $settings ) {
		$saved    = get_option( 'fnc_settings', array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$settings = wp_parse_args( $saved, fnc_defaults() );
	}

	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/**
 * فعال‌سازی افزونه: بررسی وجود کتابخانه GD و ذخیره تنظیمات پیش‌فرض.
 *
 * @return void
 */
function fnc_activate() {
	if ( false === get_option( 'fnc_settings', false ) ) {
		add_option( 'fnc_settings', fnc_defaults() );
	}
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		set_transient( 'fnc_gd_missing_notice', 1, 60 );
	}
}
register_activation_hook( __FILE__, 'fnc_activate' );

/**
 * نمایش هشدار در صورت نبودن کتابخانه GD.
 *
 * @return void
 */
function fnc_admin_notices() {
	if ( ! function_exists( 'imagecreatetruecolor' ) && current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'افزونه «کپچای عددی فارسی» برای ساختن تصویر کد امنیتی به کتابخانه GD در PHP نیاز دارد، اما این کتابخانه روی سرور شما فعال نیست. لطفاً از هاست خود بخواهید افزونه php-gd را فعال کند.', 'farsi-numeric-captcha' );
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'fnc_admin_notices' );

/**
 * لینک تنظیمات در صفحه افزونه‌ها.
 *
 * @param array $links لینک‌های فعلی.
 * @return array
 */
function fnc_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=fnc-settings' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'farsi-numeric-captcha' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fnc_action_links' );

// راه‌اندازی.
FNC_Forms::init();

if ( is_admin() ) {
	FNC_Settings::init();
}
