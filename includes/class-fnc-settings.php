<?php
/**
 * صفحه تنظیمات افزونه.
 *
 * @package FarsiNumericCaptcha
 */

defined( 'ABSPATH' ) || exit;

/**
 * کلاس FNC_Settings
 */
class FNC_Settings {

	/**
	 * راه‌اندازی.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * افزودن صفحه در منوی «تنظیمات».
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_options_page(
			__( 'کپچای عددی فارسی', 'farsi-numeric-captcha' ),
			__( 'کپچای فارسی', 'farsi-numeric-captcha' ),
			'manage_options',
			'fnc-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * ثبت تنظیمات.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'fnc_settings_group',
			'fnc_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => fnc_defaults(),
			)
		);
	}

	/**
	 * پاک‌سازی ورودی‌ها.
	 *
	 * @param array $input ورودی فرم.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = fnc_defaults();

		foreach ( array( 'enable_comments', 'enable_login', 'enable_register', 'enable_lostpassword', 'hide_for_logged_in' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['length']          = isset( $input['length'] ) ? max( 3, min( 6, (int) $input['length'] ) ) : 4;
		$out['width']           = isset( $input['width'] ) ? max( 100, min( 400, (int) $input['width'] ) ) : 160;
		$out['height']          = isset( $input['height'] ) ? max( 36, min( 120, (int) $input['height'] ) ) : 55;
		$out['font_size']       = isset( $input['font_size'] ) ? max( 14, min( 60, (int) $input['font_size'] ) ) : 26;
		$out['max_attempts']    = isset( $input['max_attempts'] ) ? max( 1, min( 50, (int) $input['max_attempts'] ) ) : 6;
		$out['lockout_minutes'] = isset( $input['lockout_minutes'] ) ? max( 1, min( 1440, (int) $input['lockout_minutes'] ) ) : 10;
		$out['expire_minutes']  = isset( $input['expire_minutes'] ) ? max( 1, min( 120, (int) $input['expire_minutes'] ) ) : 15;

		$noise           = isset( $input['noise'] ) ? sanitize_key( $input['noise'] ) : 'medium';
		$out['noise']    = in_array( $noise, array( 'light', 'medium', 'strong' ), true ) ? $noise : 'medium';

		$digits             = isset( $input['digit_style'] ) ? sanitize_key( $input['digit_style'] ) : 'latin';
		$out['digit_style'] = in_array( $digits, array( 'latin', 'persian' ), true ) ? $digits : 'latin';

		$out['bg']         = self::sanitize_color( isset( $input['bg'] ) ? $input['bg'] : '', '#f7f7f7' );
		$out['text_color'] = self::sanitize_color( isset( $input['text_color'] ) ? $input['text_color'] : '', '#1d2327' );
		$out['label']      = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : 'کد امنیتی را وارد کنید';
		$out['placeholder'] = isset( $input['placeholder'] ) ? sanitize_text_field( $input['placeholder'] ) : '';

		if ( '' === $out['label'] ) {
			$out['label'] = 'کد امنیتی را وارد کنید';
		}

		return $out;
	}

	/**
	 * بررسی صحت کد رنگ.
	 *
	 * @param string $value    مقدار ورودی.
	 * @param string $fallback مقدار پیش‌فرض.
	 * @return string
	 */
	protected static function sanitize_color( $value, $fallback ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : $fallback;
	}

	/**
	 * نمایش صفحه تنظیمات.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$o = fnc_defaults();
		foreach ( $o as $key => $default ) {
			$o[ $key ] = fnc_option( $key );
		}

		$preview_token = FNC_Captcha::new_token();
		FNC_Captcha::store( $preview_token );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'کپچای عددی فارسی', 'farsi-numeric-captcha' ); ?></h1>
			<p><?php esc_html_e( 'یک کپچای تصویری ساده با ارقام برای فرم نظرات (و در صورت تمایل صفحه ورود). هیچ داده‌ای به سرورهای بیرونی ارسال نمی‌شود.', 'farsi-numeric-captcha' ); ?></p>

			<?php if ( ! function_exists( 'imagecreatetruecolor' ) ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'کتابخانه GD روی سرور فعال نیست؛ تا فعال‌شدن آن تصویر کپچا ساخته نمی‌شود.', 'farsi-numeric-captcha' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'پیش‌نمایش تصویر', 'farsi-numeric-captcha' ); ?></h2>
			<p>
				<img src="<?php echo esc_url( FNC_Forms::image_url( $preview_token ) ); ?>" alt="" style="border:1px solid #dcdcde;border-radius:4px;" />
			</p>
			<p class="description"><?php esc_html_e( 'پیش‌نمایش با تنظیمات ذخیره‌شده ساخته می‌شود. برای ارقام فارسی (۲۳۴) کافی است در بخش فونت، فونت دلخواه را جایگزین کنید.', 'farsi-numeric-captcha' ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( 'fnc_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'محل نمایش', 'farsi-numeric-captcha' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'فرم‌ها', 'farsi-numeric-captcha' ); ?></th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="fnc_settings[enable_comments]" value="1" <?php checked( $o['enable_comments'], 1 ); ?> /> <?php esc_html_e( 'فرم نظرات', 'farsi-numeric-captcha' ); ?></label><br />
								<label><input type="checkbox" name="fnc_settings[enable_login]" value="1" <?php checked( $o['enable_login'], 1 ); ?> /> <?php esc_html_e( 'فرم ورود (wp-login.php)', 'farsi-numeric-captcha' ); ?></label><br />
								<label><input type="checkbox" name="fnc_settings[enable_register]" value="1" <?php checked( $o['enable_register'], 1 ); ?> /> <?php esc_html_e( 'فرم ثبت‌نام', 'farsi-numeric-captcha' ); ?></label><br />
								<label><input type="checkbox" name="fnc_settings[enable_lostpassword]" value="1" <?php checked( $o['enable_lostpassword'], 1 ); ?> /> <?php esc_html_e( 'فرم بازیابی رمز عبور', 'farsi-numeric-captcha' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'کاربران واردشده', 'farsi-numeric-captcha' ); ?></th>
						<td>
							<label><input type="checkbox" name="fnc_settings[hide_for_logged_in]" value="1" <?php checked( $o['hide_for_logged_in'], 1 ); ?> /> <?php esc_html_e( 'برای کاربران واردشده کپچا نمایش داده نشود (مدیران همیشه معاف هستند)', 'farsi-numeric-captcha' ); ?></label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'ظاهر تصویر', 'farsi-numeric-captcha' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fnc-length"><?php esc_html_e( 'تعداد ارقام', 'farsi-numeric-captcha' ); ?></label></th>
						<td><input type="number" id="fnc-length" name="fnc_settings[length]" min="3" max="6" value="<?php echo esc_attr( $o['length'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'اندازه تصویر', 'farsi-numeric-captcha' ); ?></th>
						<td>
							<input type="number" name="fnc_settings[width]" min="100" max="400" value="<?php echo esc_attr( $o['width'] ); ?>" class="small-text" /> ×
							<input type="number" name="fnc_settings[height]" min="36" max="120" value="<?php echo esc_attr( $o['height'] ); ?>" class="small-text" />
							<span class="description"><?php esc_html_e( 'پیکسل (عرض × ارتفاع)', 'farsi-numeric-captcha' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fnc-digits"><?php esc_html_e( 'شکل ارقام', 'farsi-numeric-captcha' ); ?></label></th>
						<td>
							<select id="fnc-digits" name="fnc_settings[digit_style]">
								<option value="latin" <?php selected( $o['digit_style'], 'latin' ); ?>><?php esc_html_e( 'لاتین: 3 8 4 2', 'farsi-numeric-captcha' ); ?></option>
								<option value="persian" <?php selected( $o['digit_style'], 'persian' ); ?>><?php esc_html_e( 'فارسی: ۳ ۸ ۴ ۲', 'farsi-numeric-captcha' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'کاربر می‌تواند با ارقام فارسی یا لاتین پاسخ دهد؛ هر دو حالت پذیرفته می‌شود.', 'farsi-numeric-captcha' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fnc-font"><?php esc_html_e( 'اندازه فونت', 'farsi-numeric-captcha' ); ?></label></th>
						<td><input type="number" id="fnc-font" name="fnc_settings[font_size]" min="14" max="60" value="<?php echo esc_attr( $o['font_size'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="fnc-noise"><?php esc_html_e( 'میزان نویز', 'farsi-numeric-captcha' ); ?></label></th>
						<td>
							<select id="fnc-noise" name="fnc_settings[noise]">
								<option value="light" <?php selected( $o['noise'], 'light' ); ?>><?php esc_html_e( 'کم — خوانا و آسان', 'farsi-numeric-captcha' ); ?></option>
								<option value="medium" <?php selected( $o['noise'], 'medium' ); ?>><?php esc_html_e( 'متوسط (پیشنهادی)', 'farsi-numeric-captcha' ); ?></option>
								<option value="strong" <?php selected( $o['noise'], 'strong' ); ?>><?php esc_html_e( 'زیاد — سخت‌گیرانه', 'farsi-numeric-captcha' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'رنگ‌ها', 'farsi-numeric-captcha' ); ?></th>
						<td>
							<label><?php esc_html_e( 'پس‌زمینه', 'farsi-numeric-captcha' ); ?> <input type="color" name="fnc_settings[bg]" value="<?php echo esc_attr( $o['bg'] ); ?>" /></label>
							&nbsp;&nbsp;
							<label><?php esc_html_e( 'متن ارقام', 'farsi-numeric-captcha' ); ?> <input type="color" name="fnc_settings[text_color]" value="<?php echo esc_attr( $o['text_color'] ); ?>" /></label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'متن‌ها و امنیت', 'farsi-numeric-captcha' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fnc-label"><?php esc_html_e( 'برچسب فیلد', 'farsi-numeric-captcha' ); ?></label></th>
						<td><input type="text" id="fnc-label" name="fnc_settings[label]" value="<?php echo esc_attr( $o['label'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="fnc-ph"><?php esc_html_e( 'متن راهنما داخل فیلد', 'farsi-numeric-captcha' ); ?></label></th>
						<td><input type="text" id="fnc-ph" name="fnc_settings[placeholder]" value="<?php echo esc_attr( $o['placeholder'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'محدودسازی تلاش‌ها', 'farsi-numeric-captcha' ); ?></th>
						<td>
							<?php esc_html_e( 'پس از', 'farsi-numeric-captcha' ); ?>
							<input type="number" name="fnc_settings[max_attempts]" min="1" max="50" value="<?php echo esc_attr( $o['max_attempts'] ); ?>" class="small-text" />
							<?php esc_html_e( 'تلاش ناموفق، به مدت', 'farsi-numeric-captcha' ); ?>
							<input type="number" name="fnc_settings[lockout_minutes]" min="1" max="1440" value="<?php echo esc_attr( $o['lockout_minutes'] ); ?>" class="small-text" />
							<?php esc_html_e( 'دقیقه مسدود شود.', 'farsi-numeric-captcha' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'اعتبار کد', 'farsi-numeric-captcha' ); ?></th>
						<td>
							<input type="number" name="fnc_settings[expire_minutes]" min="1" max="120" value="<?php echo esc_attr( $o['expire_minutes'] ); ?>" class="small-text" />
							<?php esc_html_e( 'دقیقه (کد یک‌بارمصرف است)', 'farsi-numeric-captcha' ); ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'ذخیره تنظیمات', 'farsi-numeric-captcha' ) ); ?>
			</form>
		</div>
		<?php
	}
}
