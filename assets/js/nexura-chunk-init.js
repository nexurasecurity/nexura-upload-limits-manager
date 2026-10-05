(function($) {
    'use strict';

    function patchUploader() {
        if (typeof wp === 'undefined' || !wp.Uploader || wp.Uploader.prototype._nexuraPatched) {
            return false;
        }

        var originalInit = wp.Uploader.prototype.init;

        wp.Uploader.prototype.init = function() {
            originalInit.apply(this, arguments);
            var uploader = this.uploader;

            if (!uploader || uploader._nexuraBound) {
                return;
            }
            uploader._nexuraBound = true;

            uploader.bind('FilesAdded', function(up, files) {
                plupload.each(files, function(file) {
                    file.nexura_resume_id = resumeId(file);
                });
            });

            uploader.bind('BeforeUpload', function(up, file) {
                up.settings.multipart_params = up.settings.multipart_params || {};
                up.settings.multipart_params.nexura_resume_id = file.nexura_resume_id || resumeId(file);
                up.settings.multipart_params.nexura_total_size = file.size;
            });
        };

        wp.Uploader.prototype._nexuraPatched = true;
        return true;
    }

    function resumeId(file) {
        var nativeFile = file.getNative ? file.getNative() : null;
        var lastModified = nativeFile && nativeFile.lastModified ? nativeFile.lastModified : '0';
        return file.name + '_' + file.size + '_' + lastModified;
    }

    if (!patchUploader()) {
        var tries = 0;
        var timer = setInterval(function() {
            tries++;
            if (patchUploader() || tries > 40) {
                clearInterval(timer);
            }
        }, 250);
    }
})(jQuery);
