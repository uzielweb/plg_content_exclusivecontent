/**
 * Exclusive Content Plugin - Frontend JS
 * Handles password toggle and AJAX-style login feedback.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		const form = document.getElementById('exclusive-login-form');
		if (!form) return;

		// Password visibility toggle
		const toggleBtn = form.querySelector('.toggle-password');
		const passwordInput = form.querySelector('#exclusive-password');

		if (toggleBtn && passwordInput) {
			toggleBtn.addEventListener('click', function () {
				const isPassword = passwordInput.type === 'password';
				passwordInput.type = isPassword ? 'text' : 'password';
				toggleBtn.textContent = isPassword ? '🙈' : '👁';
			});
		}

		// Form submit handling (shows loading state)
		form.addEventListener('submit', function () {
			const btn = form.querySelector('.exclusive-btn-login');
			const errorBox = document.getElementById('exclusive-login-error');

			if (errorBox) {
				errorBox.style.display = 'none';
				errorBox.textContent = '';
			}

			if (btn) {
				btn.disabled = true;
				btn.dataset.originalText = btn.textContent;
				btn.textContent = 'Entrando...';
			}

			// Note: We let the form submit normally to com_users.
			// After successful login Joomla redirects back to the same article
			// (thanks to the "return" field) and the full content is shown.
			// This is the most reliable and secure approach.
		});
	});
})();