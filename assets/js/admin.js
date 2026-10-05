jQuery(document).ready(function($) {
	// 1. Install Nexura Security
	$("#install-nexura-security").on("click", function(e) {
		e.preventDefault();
		var btn = $(this);
		var is_installed = btn.data("installed") === true || btn.data("installed") === "true";
		btn.text(is_installed ? "Activating..." : "Installing...").prop("disabled", true);
		
		$.post(ajaxurl, {
			action: "nexura_install_security",
			nonce: nexura_admin_vars.install_nonce
		}, function(response) {
			if (response.success) {
				btn.text("Installed & Activated!").css("background-color", "#00b859");
			} else {
				btn.text("Failed! Try Again").prop("disabled", false).css("background-color", "#dc3232");
			}
		});
	});

	// 2. Edit User Quota Logic
	$(document).on("click", ".nexura-edit-quota-btn", function(e) {
		e.preventDefault();
		var userId = $(this).data("user");
		$(".nexura-quota-view-" + userId).hide();
		$(".nexura-quota-edit-" + userId).show();
	});

	$(document).on("click", ".nexura-cancel-quota-btn", function(e) {
		e.preventDefault();
		var userId = $(this).data("user");
		$(".nexura-quota-edit-" + userId).hide();
		$(".nexura-quota-view-" + userId).show();
	});

	// Helper function for inline notices
	function showNotice(msg, type) {
		var notice = $("#nexura-global-notice");
		if (notice.length === 0) {
			alert(msg);
			return;
		}
		var icon = type === 'error' ? '✖ ' : '✓ ';
		notice.text(icon + msg).css({
			'background': type === 'error' ? '#fef2f2' : '#ecfdf5',
			'color': type === 'error' ? '#991b1b' : '#065f46',
			'border-color': type === 'error' ? '#fecaca' : '#a7f3d0',
			'display': 'inline-block'
		}).fadeIn();
		setTimeout(function() {
			notice.fadeOut();
		}, 3000);
	}

	$(document).on("click", ".nexura-save-quota-btn", function(e) {
		e.preventDefault();
		var btn = $(this);
		var userId = btn.data("user");
		var nonce = btn.data("nonce");
		var quota = $(".nexura-quota-input-" + userId).val();
		var spinner = $(".nexura-quota-spinner-" + userId);

		btn.prop("disabled", true);
		spinner.addClass("is-active");

		$.post(ajaxurl, {
			action: "nexura_save_user_quota",
			nonce: nonce,
			user_id: userId,
			quota: quota
		}, function(response) {
			if (response.success) {
				showNotice("Quota updated successfully!", "success");
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				showNotice("Error: " + response.data.message, "error");
				btn.prop("disabled", false);
				spinner.removeClass("is-active");
			}
		}).fail(function() {
			showNotice("A server error occurred while saving.", "error");
			btn.prop("disabled", false);
			spinner.removeClass("is-active");
		});
	});

	// 3. Add New User Quota
	$(document).on("click", "#nexura-add-quota-btn", function(e) {
		e.preventDefault();
		var btn = $(this);
		var userId = $("#new_quota_user_id").val();
		var quota = $("#new_quota_input").val();
		var nonce = btn.data("nonce");
		var spinner = $(".nexura-add-quota-spinner");

		if (!userId || userId === "-1" || userId === "") {
			showNotice("Please select a user.", "error");
			return;
		}

		if (!quota) {
			showNotice("Please enter a quota.", "error");
			return;
		}

		btn.prop("disabled", true);
		spinner.addClass("is-active");

		$.post(ajaxurl, {
			action: "nexura_save_user_quota",
			nonce: nonce,
			user_id: userId,
			quota: quota
		}, function(response) {
			if (response.success) {
				showNotice("Quota added successfully!", "success");
				setTimeout(function() {
					location.reload();
				}, 1000);
			} else {
				showNotice("Error: " + response.data.message, "error");
				btn.prop("disabled", false);
				spinner.removeClass("is-active");
			}
		}).fail(function() {
			showNotice("A server error occurred while saving.", "error");
			btn.prop("disabled", false);
			spinner.removeClass("is-active");
		});
	});

	// Live Search for user quotas
	$('#nexura-user-search').on('keyup', function() {
		var value = $(this).val().toLowerCase();
		$('.nexura-search-item').filter(function() {
			$(this).toggle($(this).data('search').indexOf(value) > -1);
		});
	});

});

// 4. Initialize Storage Chart
document.addEventListener('DOMContentLoaded', function() {
	var canvas = document.getElementById('storageChart');
	if ( canvas && typeof nexura_admin_vars !== 'undefined' && nexura_admin_vars.chart_labels ) {
		var ctx = canvas.getContext('2d');
		
		var chartLabels = nexura_admin_vars.chart_labels;
		var chartData = nexura_admin_vars.chart_data_size;
		var chartColors = nexura_admin_vars.chart_colors;

		var totalSize = chartData.reduce(function(a, b) { return a + b; }, 0);
		if (totalSize === 0) {
			chartLabels = ['Empty Storage'];
			chartData = [1];
			chartColors = ['#f0f0f1'];
		}

		var data = {
			labels: chartLabels,
			datasets: [{
				data: chartData,
				backgroundColor: chartColors,
				borderWidth: 2,
				borderColor: '#ffffff'
			}]
		};

		new Chart(ctx, {
			type: 'doughnut',
			data: data,
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '75%',
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function(context) {
								var size_mb = (context.raw / 1048576).toFixed(2);
								return context.label + ': ' + size_mb + ' MB';
							}
						}
					}
				}
			}
		});
	}
});
