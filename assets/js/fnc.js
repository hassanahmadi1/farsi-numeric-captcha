/**
 * کپچای عددی فارسی — دکمه «تصویر جدید» و پاک‌سازی ورودی.
 */
(function () {
	'use strict';

	var data = window.fncData || {};

	/**
	 * پیدا کردن نزدیک‌ترین فیلد کپچا.
	 *
	 * @param {Element} el عنصر شروع.
	 * @return {Element|null}
	 */
	function fieldOf(el) {
		while (el && el !== document) {
			if (el.classList && el.classList.contains('fnc-field')) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	/**
	 * تبدیل ارقام فارسی/عربی به لاتین.
	 *
	 * @param {string} value مقدار.
	 * @return {string}
	 */
	function toLatinDigits(value) {
		var fa = '۰۱۲۳۴۵۶۷۸۹';
		var ar = '٠١٢٣٤٥٦٧٨٩';
		var out = '';

		for (var i = 0; i < value.length; i++) {
			var ch = value.charAt(i);
			var faIndex = fa.indexOf(ch);
			var arIndex = ar.indexOf(ch);

			if (faIndex > -1) {
				out += String(faIndex);
			} else if (arIndex > -1) {
				out += String(arIndex);
			} else {
				out += ch;
			}
		}

		return out.replace(/\D/g, '');
	}

	/**
	 * درخواست تصویر تازه از سرور.
	 *
	 * @param {Element} field فیلد کپچا.
	 */
	function refresh(field) {
		if (!field || !data.ajaxUrl || field.getAttribute('data-busy') === '1') {
			return;
		}

		var img = field.querySelector('.fnc-img');
		var button = field.querySelector('.fnc-refresh');
		var tokenInput = field.querySelector('input[name="fnc_token"]');
		var codeInput = field.querySelector('.fnc-input');

		if (!img || !button || !tokenInput) {
			// اگر اسکریپت در دسترس نبود، فقط تصویر فعلی را دوباره بارگذاری می‌کنیم.
			if (img) {
				img.src = img.src.split('&_fnc=')[0] + '&_fnc=' + Date.now();
			}
			return;
		}

		field.setAttribute('data-busy', '1');
		button.classList.add('is-loading');

		var body = new URLSearchParams();
		body.append('action', data.action || 'fnc_refresh');

		fetch(data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data || !json.data.url) {
					throw new Error('bad response');
				}

				img.src = json.data.url;
				tokenInput.value = json.data.token;

				if (codeInput) {
					codeInput.value = '';
					codeInput.classList.remove('fnc-error', 'fnc-ok');
					codeInput.focus();
				}
			})
			.catch(function () {
				// در صورت خطا، تصویر قبلی با یک پارامتر اضافه دوباره بارگذاری می‌شود.
				img.src = img.src.split('&_fnc=')[0] + '&_fnc=' + Date.now();
			})
			.finally(function () {
				field.removeAttribute('data-busy');
				button.classList.remove('is-loading');
			});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('.fnc-refresh') : null;

		if (!button) {
			return;
		}

		event.preventDefault();
		refresh(fieldOf(button));
	});

	document.addEventListener('input', function (event) {
		var input = event.target;

		if (!input.classList || !input.classList.contains('fnc-input')) {
			return;
		}

		var cleaned = toLatinDigits(input.value);

		if (cleaned !== input.value) {
			input.value = cleaned;
		}

		input.classList.remove('fnc-error', 'fnc-ok');
	});

	// اگر تصویر به هر دلیلی بارگذاری نشد، یک‌بار تلاش دوباره.
	document.addEventListener(
		'error',
		function (event) {
			var img = event.target;

			if (!img.classList || !img.classList.contains('fnc-img') || img.getAttribute('data-fnc-retried') === '1') {
				return;
			}

			img.setAttribute('data-fnc-retried', '1');
			img.src = img.src + (img.src.indexOf('?') > -1 ? '&' : '?') + '_fnc=' + Date.now();
		},
		true
	);
})();
