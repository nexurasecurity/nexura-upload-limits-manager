(function () {
	'use strict';

	if (typeof nexuraRestChunk === 'undefined') {
		return;
	}

	// wp_localize_script turns numbers into strings. Adding that string
	// concatenates, so later parts were larger than the server allows.
	nexuraRestChunk.chunkSize = parseInt(nexuraRestChunk.chunkSize, 10) || 524288;
	nexuraRestChunk.singleLimit = parseInt(nexuraRestChunk.singleLimit, 10) || nexuraRestChunk.chunkSize;

	function isMediaCreate(options) {
		if (!options || String(options.method || 'GET').toUpperCase() !== 'POST') {
			return false;
		}
		if (!(options.body instanceof FormData) || typeof options.body.get !== 'function') {
			return false;
		}
		var path = String(options.path || options.url || '');
		if (path.indexOf('/wp/v2/media') === -1 || path.indexOf('/sideload') !== -1) {
			return false;
		}
		var file = options.body.get('file');
		return file && typeof file.size === 'number' && file.size > nexuraRestChunk.singleLimit;
	}

	function extraFields(formData) {
		var extra = {};
		formData.forEach(function (value, key) {
			if (key !== 'file' && typeof value === 'string' && value !== '') {
				extra[key] = value;
			}
		});
		return extra;
	}

	function postPart(file, index, chunks, extra) {
		var start = index * nexuraRestChunk.chunkSize;
		var blob = file.slice(start, Math.min(file.size, start + nexuraRestChunk.chunkSize));
		var body = new FormData();
		body.append('action', 'nexura_rest_media_chunk');
		body.append('_ajax_nonce', nexuraRestChunk.nonce);
		body.append('chunk', String(index));
		body.append('chunks', String(chunks));
		body.append('name', file.name || 'upload');
		body.append('nexura_total_size', String(file.size));
		body.append('nexura_resume_id', (file.name || 'upload') + '_' + file.size + '_' + (file.lastModified || 0));
		body.append('nexura_part', blob, file.name || 'upload');
		Object.keys(extra).forEach(function (key) {
			body.append(key, extra[key]);
		});

		return fetch(nexuraRestChunk.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			return response.text().then(function (text) {
				try {
					return JSON.parse(text);
				} catch (error) {
					throw new Error('Upload failed.');
				}
			});
		});
	}

	function wait(ms) {
		return new Promise(function (resolve) {
			setTimeout(resolve, ms);
		});
	}

	function sendPart(file, index, chunks, extra, attempt) {
		return postPart(file, index, chunks, extra).then(function (payload) {
			if (!payload || !payload.success) {
				var message = payload && payload.data && payload.data.message ? payload.data.message : 'Upload failed.';
				var code = payload && payload.data ? payload.data.code : '';
				if (code === 'finalizing' && attempt < 40) {
					return wait(1500).then(function () {
						return sendPart(file, index, chunks, extra, attempt + 1);
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
					return sendPart(file, index, chunks, extra, attempt + 1);
				});
			}
			throw error;
		});
	}

	function uploadInParts(formData) {
		var file = formData.get('file');
		var extra = extraFields(formData);
		var chunks = Math.max(1, Math.ceil(file.size / nexuraRestChunk.chunkSize));
		var index = 0;

		function next() {
			return sendPart(file, index, chunks, extra, 0).then(function (data) {
				if (data.attachment) {
					return data.attachment;
				}
				index += 1;
				if (index < chunks) {
					return next();
				}
				throw new Error('Upload failed.');
			});
		}

		return next();
	}

	function installMiddleware() {
		if (nexuraRestChunk._patched) {
			return true;
		}
		if (!window.wp || !wp.apiFetch || !wp.apiFetch.use) {
			return false;
		}
		nexuraRestChunk._patched = true;
		wp.apiFetch.use(function (options, next) {
			if (!isMediaCreate(options)) {
				return next(options);
			}
			return uploadInParts(options.body);
		});
		return true;
	}

	if (!installMiddleware()) {
		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			if (installMiddleware() || tries > 40) {
				clearInterval(timer);
			}
		}, 250);
	}
})();
