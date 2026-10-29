<?php
/**
 * اتصال کپچا به فرم‌های وردپرس (نظرات، ورود، ثبت‌نام، بازیابی رمز) و نقطه‌های AJAX.
 *
 * @package FarsiNumericCaptcha
 */

defined( 'ABSPATH' ) || exit;

/**
 * کلاس FNC_Forms
 */
class FNC_Forms {

	/**
	 * راه‌اندازی قلاب‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		// فرم نظرات.
		add_action( 'comment_form_after_fields', array( __CLASS__, 'render_field' ) );
		add_action( 'comment_form_logged_in_after', array( __CLASS__, 'render_field' ) );
		add_action( 'pre_comment_on_post', array( __CLASS__, 'validate_comment' ) );

		// صفحه ورود.
		add_action( 'login_form', array( __CLASS__, 'render_field' ) );
		add_filter( 'authenticate', array( __CLASS__, 'validate_login' ), 99, 3 );

		// ثبت‌نام.
		add_action( 'register_form', array( __CLASS__, 'render_field' ) );
		add_filter( 'registration_errors', array( __CLASS__, 'validate_register' ), 10, 3 );

		// بازیابی رمز.
		add_action( 'lostpassword_form', array( __CLASS__, 'render_field' ) );
		add_action( 'lostpassword_post', array( __CLASS__, 'validate_lostpassword' ) );

		// پوسته‌ها و اسکریپت‌ها.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );

		// درخواست‌های AJAX (تصویر و کد جدید).
		add_action( 'wp_ajax_fnc_image', array( __CLASS__, 'ajax_image' ) );
		add_action( 'wp_ajax_nopriv_fnc_image', array( __CLASS__, 'ajax_image' ) );
		add_action( 'wp_ajax_fnc_refresh', array( __CLASS__, 'ajax_refresh' ) );
		add_action( 'wp_ajax_nopriv_fnc_refresh', array( __CLASS__, 'ajax_refresh' ) );
	}

	/**
	 * تشخیص این‌که فرم جاری کدام است.
	 *
	 * @return string comments|login|register|lostpassword
	 */
	protected static function current_context() {
		$map = array(
			'comment_form_after_fields'       => 'comments',
			'comment_form_logged_in_after'    => 'comments',
			'login_form'                      => 'login',
			'register_form'                   => 'register',
			'lostpassword_form'               => 'lostpassword',
		);

		$filter = current_filter();

		return isset( $map[ $filter ] ) ? $map[ $filter ] : 'comments';
	}

	/**
	 * آیا کپچا برای این فرم باید نشان داده شود؟
	 *
	 * @param string $context نام فرم.
	 * @return bool
	 */
	public static function should_enforce( $context ) {
		if ( ! fnc_option( 'enable_' . $context ) ) {
			return false;
		}

		if ( 'comments' === $context ) {
			// کاربران واردشده (در صورت انتخاب) و کسانی که اجازه مدیریت دیدگاه دارند، معاف‌اند.
			if ( fnc_option( 'hide_for_logged_in' ) && is_user_logged_in() ) {
				return false;
			}

			if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * آدرس تصویر کپچا.
	 *
	 * @param string $token توکن.
	 * @return string
	 */
	public static function image_url( $token ) {
		$url = add_query_arg(
			array(
				'action' => 'fnc_image',
				'token'  => $token,
			),
			admin_url( 'admin-ajax.php' )
		);

		/**
		 * امکان تغییر آدرس تصویر کپچا.
		 *
		 * @param string $url   آدرس تصویر.
		 * @param string $token توکن کپچا.
		 */
		return apply_filters( 'fnc_image_url', $url, $token );
	}

	/**
	 * چاپ فیلد کپچا در فرم.
	 *
	 * @return void
	 */
	public static function render_field() {
		$context = self::current_context();

		if ( ! self::should_enforce( $context ) ) {
			return;
		}

		$token = FNC_Captcha::new_token();
		FNC_Captcha::store( $token );

		$width  = max( 100, min( 400, (int) fnc_option( 'width' ) ) );
		$height = max( 36, min( 120, (int) fnc_option( 'height' ) ) );
		$field  = 'fnc_code_' . $token;
		$length = max( 3, min( 6, (int) fnc_option( 'length' ) ) );

		self::register_assets();
		wp_enqueue_style( 'fnc-captcha' );
		wp_enqueue_script( 'fnc-captcha' );

		ob_start();
		?>
		<p class="fnc-field" data-fnc-field="1">
			<label class="fnc-label" for="<?php echo esc_attr( $field ); ?>">
				<?php echo esc_html( fnc_option( 'label' ) ); ?>
				<span class="fnc-required">*</span>
			</label>
			<span class="fnc-media">
				<img src="<?php echo esc_url( self::image_url( $token ) ); ?>"
					class="fnc-img"
					alt="<?php esc_attr_e( 'کد امنیتی', 'farsi-numeric-captcha' ); ?>"
					width="<?php echo esc_attr( $width ); ?>"
					height="<?php echo esc_attr( $height ); ?>"
					decoding="async" />
				<button type="button" class="fnc-refresh" title="<?php esc_attr_e( 'تصویر جدید', 'farsi-numeric-captcha' ); ?>" aria-label="<?php esc_attr_e( 'نمایش کد امنیتی جدید', 'farsi-numeric-captcha' ); ?>">
					<span aria-hidden="true">&#10227;</span>
				</button>
			</span>
			<input type="text"
				class="fnc-input"
				name="fnc_code"
				id="<?php echo esc_attr( $field ); ?>"
				value=""
				size="<?php echo esc_attr( $length + 2 ); ?>"
				maxlength="<?php echo esc_attr( $length + 1 ); ?>"
				inputmode="numeric"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="off"
				spellcheck="false"
				dir="ltr"
				placeholder="<?php echo esc_attr( fnc_option( 'placeholder' ) ); ?>"
				required="required" />
			<input type="hidden" name="fnc_token" value="<?php echo esc_attr( $token ); ?>" />
		</p>
		<?php
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی بالا کامل اسکیپ شده است.
	}

	/**
	 * ثبت فایل‌های CSS/JS.
	 *
	 * @return void
	 */
	protected static function register_assets() {
		if ( ! wp_style_is( 'fnc-captcha', 'registered' ) ) {
			wp_register_style( 'fnc-captcha', FNC_URL . 'assets/css/fnc.css', array(), FNC_VERSION );
		}

		if ( ! wp_script_is( 'fnc-captcha', 'registered' ) ) {
			wp_register_script( 'fnc-captcha', FNC_URL . 'assets/js/fnc.js', array(), FNC_VERSION, true );
			wp_localize_script(
				'fnc-captcha',
				'fncData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => 'fnc_refresh',
					'loading' => __( 'در حال ساخت تصویر…', 'farsi-numeric-captcha' ),
					'error'   => __( 'ساخت تصویر جدید ممکن نشد. لطفاً صفحه را دوباره بارگذاری کنید.', 'farsi-numeric-captcha' ),
				)
			);
		}
	}

	/**
	 * بارگذاری فایل‌های CSS/JS در بخش عمومی سایت.
	 *
	 * @return void
	 */
	public static function enqueue() {
		self::register_assets();

		if ( is_admin() ) {
			return;
		}

		$needed    = false;
		$on_login  = in_array( isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '', array( 'wp-login.php', 'wp-register.php' ), true );
		$in_login  = did_action( 'login_enqueue_scripts' ) > 0 || 'login_enqueue_scripts' === current_filter();

		if ( fnc_option( 'enable_comments' ) && is_singular() && comments_open() ) {
			$needed = true;
		}

		if ( ! $needed && ( $on_login || $in_login ) && ( fnc_option( 'enable_login' ) || fnc_option( 'enable_register' ) || fnc_option( 'enable_lostpassword' ) ) ) {
			$needed = true;
		}

		if ( $needed ) {
			wp_enqueue_style( 'fnc-captcha' );
			wp_enqueue_script( 'fnc-captcha' );
		}
	}

	/**
	 * بارگذاری در صفحه تنظیمات افزونه.
	 *
	 * @param string $hook شناسه صفحه.
	 * @return void
	 */
	public static function enqueue_admin( $hook ) {
		if ( 'settings_page_fnc-settings' !== $hook ) {
			return;
		}

		self::register_assets();
		wp_enqueue_style( 'fnc-captcha' );
		wp_enqueue_script( 'fnc-captcha' );
	}

	/**
	 * خروجی تصویر کپچا.
	 *
	 * @return void
	 */
	public static function ajax_image() {
		nocache_headers();
		header( 'Content-Type: image/png' );
		header( 'X-Robots-Tag: noindex, nofollow', true );

		$token = isset( $_GET['token'] ) ? FNC_Captcha::clean_token( sanitize_text_field( wp_unslash( $_GET['token'] ) ) ) : '';

		if ( '' === $token || ! function_exists( 'imagecreatetruecolor' ) ) {
			status_header( 400 );
			exit;
		}

		$code = FNC_Captcha::get_code( $token );
		$png  = FNC_Captcha::png( $code );

		if ( false === $png ) {
			status_header( 500 );
			exit;
		}

		header( 'Content-Length: ' . strlen( $png ) );
		echo $png; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی باینری تصویر.
		exit;
	}

	/**
	 * ساخت توکن و کد تازه برای دکمه «تصویر جدید».
	 *
	 * @return void
	 */
	public static function ajax_refresh() {
		// محدودسازی ساده برای جلوگیری از انبوه‌شدن درخواست‌ها.
		$ip_key = 'fnc_rl_' . md5( ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) . '|' . wp_salt( 'auth' ) );
		$count  = (int) get_transient( $ip_key );

		if ( $count > 120 ) {
			wp_send_json_error( array( 'message' => __( 'درخواست‌های بیش از حد. کمی بعد دوباره تلاش کنید.', 'farsi-numeric-captcha' ) ), 429 );
		}

		set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			wp_send_json_error( array( 'message' => __( 'کتابخانه GD روی سرور فعال نیست.', 'farsi-numeric-captcha' ) ), 500 );
		}

		$token = FNC_Captcha::new_token();
		FNC_Captcha::store( $token );

		wp_send_json_success(
			array(
				'token' => $token,
				'url'   => self::image_url( $token ),
			)
		);
	}

	/**
	 * بررسی کد ارسال‌شده.
	 *
	 * @return true|WP_Error
	 */
	protected static function check_code() {
		if ( FNC_Captcha::is_locked() ) {
			return new WP_Error(
				'fnc_locked',
				sprintf(
					/* translators: %d: تعداد دقیقه */
					__( 'تعداد تلاش‌های ناموفق شما زیاد بوده است. لطفاً %d دقیقه دیگر دوباره تلاش کنید.', 'farsi-numeric-captcha' ),
					max( 1, (int) fnc_option( 'lockout_minutes' ) )
				)
			);
		}

		$token = isset( $_POST['fnc_token'] ) ? sanitize_text_field( wp_unslash( $_POST['fnc_token'] ) ) : '';
		$code  = isset( $_POST['fnc_code'] ) ? sanitize_text_field( wp_unslash( $_POST['fnc_code'] ) ) : '';

		if ( '' === trim( $code ) || '' === $token ) {
			return new WP_Error( 'fnc_empty', __( 'لطفاً کد امنیتی تصویر را وارد کنید.', 'farsi-numeric-captcha' ) );
		}

		if ( FNC_Captcha::verify( $token, $code ) ) {
			FNC_Captcha::clear_failures();
			return true;
		}

		FNC_Captcha::register_failure();

		return new WP_Error( 'fnc_invalid', __( 'کد امنیتی صحیح نیست. لطفاً کد تصویر را دوباره و با دقت وارد کنید.', 'farsi-numeric-captcha' ) );
	}

	/**
	 * اعتبارسنجی فرم نظر.
	 *
	 * @param int $post_id شناسه نوشته.
	 * @return void
	 */
	public static function validate_comment( $post_id ) {
		if ( ! self::should_enforce( 'comments' ) ) {
			return;
		}

		// مدیران و ویرایشگران بدون کپچا می‌توانند نظر بدهند.
		if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
			return;
		}

		$result = self::check_code();

		if ( ! is_wp_error( $result ) ) {
			return;
		}

		wp_die(
			'<p>' . esc_html( $result->get_error_message() ) . '</p>',
			esc_html__( 'کد امنیتی', 'farsi-numeric-captcha' ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}

	/**
	 * اعتبارسنجی ورود.
	 *
	 * @param null|WP_User|WP_Error $user     نتیجه تا این مرحله.
	 * @param string                $username نام کاربری.
	 * @param string                $password رمز عبور.
	 * @return null|WP_User|WP_Error
	 */
	public static function validate_login( $user, $username, $password ) {
		if ( ! self::should_enforce( 'login' ) ) {
			return $user;
		}

		// درخواست‌های غیرفرم (XML-RPC، REST، اپلیکیشن‌ها) را دست نمی‌زنیم.
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $user;
		}

		// فقط فرم ورود خود وردپرس را بررسی می‌کنیم.
		if ( empty( $_POST['log'] ) || empty( $_POST['pwd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $user;
		}

		$result = self::check_code();

		if ( ! is_wp_error( $result ) ) {
			return $user;
		}

		// اگر نام کاربری/رمز اشتباه باشد، همان خطای وردپرس نمایش داده می‌شود.
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return $result;
	}

	/**
	 * اعتبارسنجی ثبت‌نام.
	 *
	 * @param WP_Error $errors               خطاها.
	 * @param string   $sanitized_user_login نام کاربری.
	 * @param string   $user_email           ایمیل.
	 * @return WP_Error
	 */
	public static function validate_register( $errors, $sanitized_user_login = '', $user_email = '' ) {
		if ( ! self::should_enforce( 'register' ) ) {
			return $errors;
		}

		$result = self::check_code();

		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}

		return $errors;
	}

	/**
	 * اعتبارسنجی فرم بازیابی رمز.
	 *
	 * @param WP_Error $errors خطاها.
	 * @return void
	 */
	public static function validate_lostpassword( $errors ) {
		if ( ! self::should_enforce( 'lostpassword' ) || ! is_wp_error( $errors ) ) {
			return;
		}

		$result = self::check_code();

		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}
	}
}
