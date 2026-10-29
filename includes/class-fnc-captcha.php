<?php
/**
 * موتور ساخت و بررسی کپچای عددی.
 *
 * @package FarsiNumericCaptcha
 */

defined( 'ABSPATH' ) || exit;

/**
 * کلاس FNC_Captcha
 */
class FNC_Captcha {

	/**
	 * ارقام مجاز؛ ۰ و ۱ حذف شده‌اند تا با حرف O و I اشتباه نشوند.
	 */
	const CHARS = '23456789';

	/**
	 * ساخت توکن یکتا برای یک فرم.
	 *
	 * @return string
	 */
	public static function new_token() {
		try {
			return bin2hex( random_bytes( 8 ) );
		} catch ( Exception $e ) {
			return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 16 );
		}
	}

	/**
	 * پاک‌سازی توکن دریافتی.
	 *
	 * @param string $token توکن خام.
	 * @return string توکن معتبر یا رشته خالی.
	 */
	public static function clean_token( $token ) {
		$token = is_string( $token ) ? strtolower( $token ) : '';
		$token = preg_replace( '/[^a-f0-9]/', '', $token );
		return ( 16 === strlen( $token ) ) ? $token : '';
	}

	/**
	 * ساخت کد عددی تصادفی.
	 *
	 * @param int|null $length تعداد ارقام.
	 * @return string
	 */
	public static function generate_code( $length = null ) {
		$length = null === $length ? (int) fnc_option( 'length' ) : (int) $length;
		$length = max( 3, min( 6, $length ) );
		$max    = strlen( self::CHARS ) - 1;
		$code   = '';

		for ( $i = 0; $i < $length; $i++ ) {
			try {
				$index = random_int( 0, $max );
			} catch ( Exception $e ) {
				$index = wp_rand( 0, $max );
			}
			$code .= self::CHARS[ $index ];
		}

		return $code;
	}

	/**
	 * ذخیره کد برای یک توکن.
	 *
	 * @param string $token توکن.
	 * @param string $code  کد عددی.
	 * @return string کد ذخیره‌شده.
	 */
	public static function store( $token, $code = null ) {
		$code = null === $code ? self::generate_code() : $code;
		$ttl  = max( 1, (int) fnc_option( 'expire_minutes' ) ) * MINUTE_IN_SECONDS;

		set_transient(
			'fnc_' . $token,
			array(
				'code' => $code,
				'time' => time(),
			),
			$ttl
		);

		return $code;
	}

	/**
	 * گرفتن کد ذخیره‌شده؛ اگر منقضی شده باشد کد تازه ساخته می‌شود.
	 *
	 * @param string $token توکن.
	 * @return string
	 */
	public static function get_code( $token ) {
		$data = get_transient( 'fnc_' . $token );

		if ( is_array( $data ) && ! empty( $data['code'] ) ) {
			return (string) $data['code'];
		}

		return self::store( $token );
	}

	/**
	 * تبدیل ارقام فارسی/عربی به لاتین و حذف فاصله‌ها.
	 *
	 * @param string $value ورودی کاربر.
	 * @return string
	 */
	public static function normalize_digits( $value ) {
		$value = is_string( $value ) ? $value : '';
		$value = str_replace(
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ),
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			$value
		);

		return trim( preg_replace( '/\s+/', '', $value ) );
	}

	/**
	 * تبدیل ارقام لاتین به ارقام فارسی برای نمایش روی تصویر.
	 *
	 * @param string $code کد لاتین.
	 * @return string
	 */
	public static function display_code( $code ) {
		if ( 'persian' !== fnc_option( 'digit_style' ) ) {
			return $code;
		}

		return str_replace(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
			(string) $code
		);
	}

	/**
	 * بررسی صحت کد واردشده (کد یک‌بارمصرف است).
	 *
	 * @param string $token توکن فرم.
	 * @param string $input کد واردشده توسط کاربر.
	 * @return bool
	 */
	public static function verify( $token, $input ) {
		$token = self::clean_token( $token );
		$input = self::normalize_digits( $input );

		if ( '' === $token || '' === $input ) {
			return false;
		}

		$key  = 'fnc_' . $token;
		$data = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['code'] ) ) {
			return false;
		}

		$ttl = max( 1, (int) fnc_option( 'expire_minutes' ) ) * MINUTE_IN_SECONDS;
		if ( isset( $data['time'] ) && ( time() - (int) $data['time'] ) > $ttl ) {
			delete_transient( $key );
			return false;
		}

		if ( ! hash_equals( (string) $data['code'], $input ) ) {
			return false;
		}

		// یک‌بارمصرف: پس از پاسخ درست حذف می‌شود. در صورت خطا باقی می‌ماند
		// تا اگر صفحه از کش بارگذاری شد، کاربر بتواند همان تصویر را دوباره وارد کند
		// (تعداد تلاش‌ها همچنان محدود است).
		delete_transient( $key );
		return true;
	}

	/**
	 * کلید مخصوص هر بازدیدکننده برای محدودسازی تلاش‌ها.
	 *
	 * @return string
	 */
	protected static function ip_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'fnc_try_' . md5( $ip . '|' . wp_salt( 'auth' ) );
	}

	/**
	 * آیا بازدیدکننده به‌خاطر تلاش‌های ناموفق موقتاً قفل شده است؟
	 *
	 * @return bool
	 */
	public static function is_locked() {
		$data = get_transient( self::ip_key() );

		if ( ! is_array( $data ) || empty( $data['count'] ) ) {
			return false;
		}

		return (int) $data['count'] >= max( 1, (int) fnc_option( 'max_attempts' ) );
	}

	/**
	 * ثبت یک تلاش ناموفق.
	 *
	 * @return void
	 */
	public static function register_failure() {
		$key  = self::ip_key();
		$ttl  = max( 1, (int) fnc_option( 'lockout_minutes' ) ) * MINUTE_IN_SECONDS;
		$data = get_transient( $key );
		$data = is_array( $data ) ? $data : array( 'count' => 0 );

		$data['count'] = (int) $data['count'] + 1;

		set_transient( $key, $data, $ttl );
	}

	/**
	 * پاک کردن سابقه تلاش ناموفق پس از موفقیت.
	 *
	 * @return void
	 */
	public static function clear_failures() {
		delete_transient( self::ip_key() );
	}

	/**
	 * مسیر فونت تصویر کپچا.
	 *
	 * @return string|false
	 */
	public static function font_path() {
		$font = FNC_DIR . 'assets/fonts/DejaVuSans-Bold.ttf';

		$candidates = array(
			$font,
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
			'C:/Windows/Fonts/arialbd.ttf',
		);

		/**
		 * امکان تغییر فونت کپچا.
		 *
		 * @param array $candidates فهرست مسیرهای ممکن فونت.
		 */
		$candidates = apply_filters( 'fnc_font_candidates', $candidates );

		foreach ( $candidates as $path ) {
			if ( is_string( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * تبدیل رنگ هگز به آرایه RGB.
	 *
	 * @param string $hex کد رنگ.
	 * @param array  $fallback رنگ پیش‌فرض.
	 * @return array
	 */
	protected static function hex2rgb( $hex, $fallback = array( 247, 247, 247 ) ) {
		$hex = ltrim( (string) $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return $fallback;
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * ساخت تصویر PNG کپچا به صورت باینری.
	 *
	 * @param string $code کد عددی.
	 * @return string|false محتوای PNG یا false در صورت خطا.
	 */
	public static function png( $code ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}

		$width  = max( 100, min( 400, (int) fnc_option( 'width' ) ) );
		$height = max( 36, min( 120, (int) fnc_option( 'height' ) ) );
		$length = max( 3, strlen( $code ) );

		$im = imagecreatetruecolor( $width, $height );
		if ( ! $im ) {
			return false;
		}

		$bg_rgb   = self::hex2rgb( fnc_option( 'bg' ), array( 247, 247, 247 ) );
		$text_rgb = self::hex2rgb( fnc_option( 'text_color' ), array( 29, 35, 39 ) );

		// پس‌زمینه با گرادیان بسیار ملایم.
		$top    = imagecolorallocate( $im, $bg_rgb[0], $bg_rgb[1], $bg_rgb[2] );
		$bottom = imagecolorallocate(
			$im,
			max( 0, $bg_rgb[0] - 10 ),
			max( 0, $bg_rgb[1] - 10 ),
			max( 0, $bg_rgb[2] - 10 )
		);

		for ( $y = 0; $y < $height; $y++ ) {
			$ratio = $y / max( 1, $height - 1 );
			$r     = (int) round( $bg_rgb[0] * ( 1 - $ratio ) + max( 0, $bg_rgb[0] - 10 ) * $ratio );
			$g     = (int) round( $bg_rgb[1] * ( 1 - $ratio ) + max( 0, $bg_rgb[1] - 10 ) * $ratio );
			$b     = (int) round( $bg_rgb[2] * ( 1 - $ratio ) + max( 0, $bg_rgb[2] - 10 ) * $ratio );
			$line  = imagecolorallocate( $im, $r, $g, $b );
			imageline( $im, 0, $y, $width, $y, $line );
		}

		$noise_level = fnc_option( 'noise' );
		$noise_map   = array(
			'light'  => array( 40, 0, 100 ),
			'medium' => array( 110, 1, 88 ),
			'strong' => array( 200, 2, 72 ),
		);
		$noise_map   = isset( $noise_map[ $noise_level ] ) ? $noise_map[ $noise_level ] : $noise_map['medium'];

		// نقطه‌های نیمه‌شفاف پراکنده (کم‌رنگ تا خوانایی حفظ شود).
		for ( $i = 0; $i < $noise_map[0]; $i++ ) {
			$dark  = (bool) wp_rand( 0, 1 );
			$base  = $dark ? 70 : 215;
			$shade = max( 0, min( 255, $base + wp_rand( -30, 30 ) ) );
			$alpha = max( 0, min( 127, $noise_map[2] + wp_rand( -12, 12 ) ) );
			$dot   = imagecolorallocatealpha( $im, $shade, $shade, $shade, $alpha );
			imagefilledellipse( $im, wp_rand( 0, $width ), wp_rand( 0, $height ), wp_rand( 1, 2 ), wp_rand( 1, 2 ), $dot );
		}

		// خطوط نازک و کم‌رنگ (بدون این‌که روی خوانایی اثر بگذارند).
		for ( $i = 0; $i < $noise_map[1]; $i++ ) {
			$curve_color = imagecolorallocatealpha( $im, wp_rand( 140, 205 ), wp_rand( 140, 205 ), wp_rand( 140, 205 ), wp_rand( 85, 110 ) );
			$start_y     = wp_rand( 5, $height - 5 );
			$end_y       = wp_rand( 5, $height - 5 );
			imageline( $im, 0, $start_y, $width, $end_y, $curve_color );
		}

		$font  = self::font_path();
		$text  = imagecolorallocate( $im, $text_rgb[0], $text_rgb[1], $text_rgb[2] );
		$shown = self::display_code( $code );

		if ( $font ) {
			self::draw_ttf_digits( $im, $shown, $font, $width, $height, $text );
		} else {
			self::draw_bitmap_digits( $im, $code, $width, $height, $text );
		}

		// یک قاب نرم دور تصویر.
		$border = imagecolorallocate( $im, max( 0, $bg_rgb[0] - 25 ), max( 0, $bg_rgb[1] - 25 ), max( 0, $bg_rgb[2] - 25 ) );
		imagerectangle( $im, 0, 0, $width - 1, $height - 1, $border );

		ob_start();
		imagepng( $im );
		$png = ob_get_clean();

		imagedestroy( $im );

		return $png;
	}

	/**
	 * رسم ارقام با فونت TTF (چرخش و جابه‌جایی ملایم).
	 *
	 * @param resource|GdImage $im     تصویر.
	 * @param string           $code   کد.
	 * @param string           $font   مسیر فونت.
	 * @param int              $width  عرض.
	 * @param int              $height ارتفاع.
	 * @param int              $color  رنگ متن.
	 * @return void
	 */
	protected static function draw_ttf_digits( $im, $code, $font, $width, $height, $color ) {
		$chars  = preg_split( '//u', $code, -1, PREG_SPLIT_NO_EMPTY );
		$chars  = is_array( $chars ) ? $chars : str_split( $code );
		$length = max( 1, count( $chars ) );
		$size   = max( 12, min( 60, (int) fnc_option( 'font_size' ) ) );
		$size   = min( $size, (int) floor( $height * 0.78 ) );

		// چیدمان: عرض هر رقم را می‌سنجیم تا فاصله‌ها متعادل باشد.
		$pad        = max( 6, (int) ( $width * 0.07 ) );
		$available  = $width - ( 2 * $pad );
		$slot       = $available / $length;
		$angle_max  = 12;
		$jitter_max = max( 2, (int) round( $height * 0.06 ) );

		// کوچک‌کردن خودکار فونت تا ارقام در عرض تصویر جا شوند.
		$probe = imagettfbbox( $size, 0, $font, '8' );
		while ( $size > 12 && $probe && abs( $probe[2] - $probe[0] ) > ( $slot * 0.86 ) ) {
			$size--;
			$probe = imagettfbbox( $size, 0, $font, '8' );
		}

		for ( $i = 0; $i < $length; $i++ ) {
			$char  = $chars[ $i ];
			$angle = wp_rand( -$angle_max, $angle_max );

			$box = imagettfbbox( $size, $angle, $font, $char );
			if ( ! $box ) {
				continue;
			}

			$char_width = abs( $box[2] - $box[0] );
			$x          = $pad + ( $i * $slot ) + ( ( $slot - $char_width ) / 2 );
			$baseline   = ( $height / 2 ) - ( ( $box[1] + $box[7] ) / 2 ) + wp_rand( -$jitter_max, $jitter_max );

			imagettftext( $im, $size, $angle, (int) round( $x ), (int) round( $baseline ), $color, $font, $char );
		}
	}

	/**
	 * رسم ارقام با فونت داخلی GD (وقتی فونت TTF در دسترس نیست).
	 *
	 * @param resource|GdImage $im     تصویر.
	 * @param string           $code   کد.
	 * @param int              $width  عرض.
	 * @param int              $height ارتفاع.
	 * @param int              $color  رنگ متن.
	 * @return void
	 */
	protected static function draw_bitmap_digits( $im, $code, $width, $height, $color ) {
		$length = strlen( $code );
		$small  = imagecreatetruecolor( $width, $height );
		imagefill( $small, 0, 0, imagecolorallocate( $small, 255, 255, 255 ) );
		$small_color = imagecolorallocate( $small, 0, 0, 0 );

		$slot = ( $width - 12 ) / $length;
		for ( $i = 0; $i < $length; $i++ ) {
			$x = 6 + ( $i * $slot ) + ( $slot / 4 );
			imagestring( $small, 5, (int) $x, (int) ( ( $height / 2 ) - 8 ), $code[ $i ], $small_color );
		}

		// بزرگ‌نمایی برای خوانایی بیشتر.
		imagecopyresampled( $im, $small, 6, (int) ( $height * 0.18 ), (int) ( $width * 0.06 ), (int) ( $height * 0.3 ), $width - 12, (int) ( $height * 0.62 ), (int) ( $width * 0.9 ), (int) ( $height * 0.35 ) );
		imagedestroy( $small );
	}
}
