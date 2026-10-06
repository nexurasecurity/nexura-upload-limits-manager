(function () {
	'use strict';

	if (typeof nexuraPackage === 'undefined' || !nexuraPackage.ajaxUrl) {
		return;
	}

	// wp_localize_script turns numbers into strings. "524288" + 524288 becomes
	// "524288524288", so every part after the first was the rest of the file.
	nexuraPackage.chunkSize = parseInt(nexuraPackage.chunkSize, 10) || 524288;
	nexuraPackage.singleLimit = parseInt(nexuraPackage.singleLimit, 10) || nexuraPackage.chunkSize;

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

	function ensureProgressStyles() {
		if (document.getElementById('nexura-package-progress-style')) {
			return;
		}
		var style = document.createElement('style');
		style.id = 'nexura-package-progress-style';
		style.textContent = [
			'.nexura-package-progress{display:inline-flex;align-items:center;gap:10px;margin:0 0 0 14px;vertical-align:middle;}',
			'.nexura-package-progress-label{font-size:13px;line-height:1.2;color:#50575e;white-space:nowrap;}',
			'.nexura-package-progress-track{display:block;width:108px;height:8px;background:#dcdcde;border-radius:99px;overflow:hidden;flex:0 0 108px;}',
			'.nexura-package-progress-fill{display:block;height:100%;width:0;background:#2271b1;border-radius:99px;transition:width .25s ease;}',
			'.nexura-package-progress-count{min-width:3.2em;font-size:13px;font-weight:600;line-height:1;color:#1d2327;font-variant-numeric:tabular-nums;letter-spacing:-0.02em;}',
			'.nexura-package-progress.is-error .nexura-package-progress-track,.nexura-package-progress.is-error .nexura-package-progress-count{display:none;}',
			'.nexura-package-progress.is-error .nexura-package-progress-label{color:#d63638;font-weight:500;}'
		].join('');
		document.head.appendChild(style);
	}

	function paintProgress(note, percent, message, isError) {
		var label = note.querySelector('.nexura-package-progress-label');
		var fill = note.querySelector('.nexura-package-progress-fill');
		var count = note.querySelector('.nexura-package-progress-count');
		if (isError) {
			note.className = 'nexura-package-progress is-error';
			if (label) {
				label.textContent = message;
			}
			return;
		}
		note.className = 'nexura-package-progress';
		if (label) {
			label.textContent = message;
		}
		if (fill) {
			fill.style.width = percent + '%';
		}
		if (count) {
			count.textContent = percent + '%';
		}
	}

	function uploadPackage(file, type, note) {
		var chunks = Math.max(1, Math.ceil(file.size / nexuraPackage.chunkSize));
		var index = 0;

		function next() {
			paintProgress(note, Math.round((index / chunks) * 100), nexuraPackage.working, false);
			return sendPart(file, index, chunks, type, 0).then(function (data) {
				index += 1;
				if (data.redirect) {
					paintProgress(note, 100, nexuraPackage.working, false);
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

				ensureProgressStyles();
				var note = form.querySelector('.nexura-package-progress');
				if (!note) {
					note = document.createElement('span');
					note.className = 'nexura-package-progress';
					note.setAttribute('role', 'status');
					note.innerHTML = '<span class="nexura-package-progress-label"></span><span class="nexura-package-progress-track" aria-hidden="true"><span class="nexura-package-progress-fill"></span></span><span class="nexura-package-progress-count"></span>';
					form.appendChild(note);
				}

				var button = form.querySelector('[type="submit"]');
				if (button) {
					button.disabled = true;
				}

				uploadPackage(file, type, note).catch(function (error) {
					paintProgress(note, 0, error && error.message ? error.message : nexuraPackage.failed, true);
					if (button) {
						button.disabled = false;
					}
				});
			});
		});
	});
})();
