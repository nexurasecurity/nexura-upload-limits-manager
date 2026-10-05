(function () {
	'use strict';

	if (typeof nexuraPackage === 'undefined' || !nexuraPackage.ajaxUrl) {
		return;
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function postPart(file, index, chunks, type) {
		var start = index * nexuraPackage.chunkSize;
		var blob = file.slice(start, Math.min(file.size, start + nexuraPackage.chunkSize));
		var body = new FormData();
		body.append('action', 'nexura_package_chunk');
		body.append('_ajax_nonce', nexuraPackage.nonce);
		body.append('package_type', type);
		body.append('chunk', String(index));
		body.append('chunks', String(chunks));
		body.append('name', file.name);
		body.append('nexura_total_size', String(file.size));
		body.append('nexura_resume_id', file.name + '_' + file.size + '_' + (file.lastModified || 0));
		body.append('nexura_part', blob, file.name);

		return fetch(nexuraPackage.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			return response.text().then(function (text) {
				try {
					return JSON.parse(text);
				} catch (error) {
					throw new Error(nexuraPackage.failed);
				}
			});
		});
	}

	function wait(ms) {
		return new Promise(function (resolve) {
			setTimeout(resolve, ms);
		});
	}

	function sendPart(file, index, chunks, type, attempt) {
		return postPart(file, index, chunks, type).then(function (payload) {
			if (!payload || !payload.success) {
				var message = payload && payload.data && payload.data.message ? payload.data.message : nexuraPackage.failed;
				var code = payload && payload.data ? payload.data.code : '';
				if (code === 'finalizing' && attempt < 40) {
					return wait(1500).then(function () {
						return sendPart(file, index, chunks, type, attempt + 1);
					});
				}
				var error = new Error(message);
				error.server = true;
				throw error;
			}
			return payload.data || {};
		}).catch(function (error) {
			if (error && error.server) {
				throw error;
			}
			if (attempt < 3) {
				return wait(800).then(function () {
					return sendPart(file, index, chunks, type, attempt + 1);
				});
			}
			throw error;
		});
	}

	function uploadPackage(file, type, note) {
		var chunks = Math.max(1, Math.ceil(file.size / nexuraPackage.chunkSize));
		var index = 0;

		function next() {
			note.textContent = nexuraPackage.working + ' ' + Math.round((index / chunks) * 100) + '%';
			return sendPart(file, index, chunks, type, 0).then(function (data) {
				index += 1;
				if (data.redirect) {
					window.location.href = data.redirect;
					return;
				}
				if (index < chunks) {
					return next();
				}
				throw new Error(nexuraPackage.failed);
			});
		}

		return next();
	}

	ready(function () {
		var forms = document.querySelectorAll('form.wp-upload-form');
		Array.prototype.forEach.call(forms, function (form) {
			form.addEventListener('submit', function (event) {
				var input = form.querySelector('input[type="file"]');
				if (!input || !input.files || !input.files[0]) {
					return;
				}

				var file = input.files[0];
				if (file.size <= nexuraPackage.singleLimit) {
					return;
				}

				var type = input.name === 'themezip' ? 'theme' : 'plugin';
				event.preventDefault();

				var note = form.querySelector('.nexura-package-progress');
				if (!note) {
					note = document.createElement('p');
					note.className = 'nexura-package-progress';
					note.style.margin = '12px 0 0';
					form.appendChild(note);
				}

				var button = form.querySelector('[type="submit"]');
				if (button) {
					button.disabled = true;
				}

				uploadPackage(file, type, note).catch(function (error) {
					note.textContent = error && error.message ? error.message : nexuraPackage.failed;
					if (button) {
						button.disabled = false;
					}
				});
			});
		});
	});
})();
