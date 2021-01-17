const { imageEdit, ajaxurl, jQuery, imageEditL10n, wp, document } = window;

let imageEditInstance = null;
let nonceInstance = null;
let currentSize = null;

imageEdit.oldInit = imageEdit.init;
imageEdit.oldOpen = imageEdit.open;

imageEdit.init = function (postId) {
	this.oldInit(postId);

	imageEditInstance = this;
};

imageEdit.open = function (postid, nonce, view) {
	nonceInstance = nonce;

	return this.oldOpen(postid, nonce, view);
};

/**
 * Binds the necessary events to the image.
 *
 * When the image source is reloaded the image will be reloaded.
 *
 * @since 2.9.0
 *
 * @memberof imageEdit
 *
 * @param {number}   postid   The post id.
 * @param {string}   nonce    The nonce to verify the request.
 * @param {Function} callback Function to execute when the image is loaded.
 *
 * @return {void}
 */
imageEdit.refreshEditor = function (postid, nonce, callback) {
	const t = this;

	t.toggleEditor(postid, 1);
	const data = {
		action: 'wpmp_edit_preview',
		_ajax_nonce: nonce,
		postid,
		history: t.filterHistory(postid, 1),
		rand: t.intval(Math.random() * 1000000),
	};

	if (currentSize) {
		data.size = currentSize;
	}

	const img = jQuery('<img id="image-preview-' + postid + '" alt="" />')
		.on('load', { history: data.history }, function (event) {
			const parent = jQuery('#imgedit-crop-' + postid);
			let historyObj;

			// Checks if there already is some image-edit history.
			if (event.data.history !== '') {
				historyObj = JSON.parse(event.data.history);
				// If last executed action in history is a crop action.
				if (historyObj[historyObj.length - 1].c) {
					/*
					 * A crop action has completed and the crop button gets disabled
					 * ensure the undo button is enabled.
					 */
					imageEdit.setDisabled(jQuery('#image-undo-' + postid), true);
					// Move focus to the undo button to avoid a focus loss.
					jQuery('#image-undo-' + postid).focus();
				}
			}

			parent.empty().append(img);

			// w, h are the new full size dimensions.
			const max1 = Math.max(t.hold.w, t.hold.h);
			const max2 = Math.max(jQuery(img).width(), jQuery(img).height());
			imageEdit.hold.sizer = max1 > max2 ? max2 / max1 : 1;

			imageEdit.initCrop(postid, img, parent);

			if (typeof callback !== 'undefined' && callback !== null) {
				callback();
			}

			if (
				jQuery('#imgedit-history-' + postid).val() &&
				jQuery('#imgedit-undone-' + postid).val() === '0'
			) {
				jQuery('input.imgedit-submit-btn', '#imgedit-panel-' + postid).removeAttr(
					'disabled',
				);
			} else {
				jQuery('input.imgedit-submit-btn', '#imgedit-panel-' + postid).prop(
					'disabled',
					true,
				);
			}

			imageEdit.toggleEditor(postid, 0);
		})
		.on('error', function () {
			jQuery('#imgedit-crop-' + postid)
				.empty()
				.append('<div class="error"><p>' + imageEditL10n.error + '</p></div>');
			t.toggleEditor(postid, 0);
		})
		.attr('src', ajaxurl + '?' + jQuery.param(data));
};

imageEdit.openSize = function (size) {
	const { postid } = this;
	const btn = jQuery('#imgedit-open-btn-' + postid);

	currentSize = size;

	/*
	 * Instead of disabling the button, which causes a focus loss and makes screen
	 * readers announce "unavailable", return if the button was already clicked.
	 */
	if (btn.hasClass('button-activated')) {
		return null;
	}

	const elem = jQuery('#image-editor-' + postid);
	const head = jQuery('#media-head-' + postid);
	const spin = btn.siblings('.spinner');

	spin.addClass('is-active');

	const data = {
		action: 'image-editor',
		_ajax_nonce: nonceInstance,
		postid,
		do: 'open',
	};

	if (size) {
		data.size = size;
	}

	const dfd = jQuery
		.ajax({
			url: ajaxurl,
			type: 'post',
			dataType: 'json',
			data,
			beforeSend() {
				btn.addClass('button-activated');
			},
		})
		.done(function (response) {
			const { html } = response.data;
			elem.html(html);
			head.fadeOut('fast', function () {
				elem.fadeIn('fast');
				btn.removeClass('button-activated');
				spin.removeClass('is-active');
			});
			// Initialise the Image Editor now that everything is ready.
			imageEdit.init(postid);
		});

	return dfd;
};

/**
 * Performs an image edit action.
 *
 * @since 2.9.0
 * @memberof imageEdit
 * @param {number} postid The post id.
 * @param {string} nonce  The nonce to verify the request.
 * @param {string} action The action to perform on the image.
 * @param {string} size Image size
 * The possible actions are: "scale" and "restore".
 * @return {boolean|void} Executes a post request that refreshes the page
 * when the action is performed.
 * Returns false if a invalid action is given,
 * or when the action cannot be performed.
 */
imageEdit.action = function (postid, nonce, action, size) {
	const t = this;
	if (t.notsaved(postid)) {
		return false;
	}

	const data = {
		action: 'image-editor',
		_ajax_nonce: nonce,
		postid,
		target: size,
	};

	if (action === 'scale') {
		const w = jQuery('#imgedit-scale-width-' + postid);
		const h = jQuery('#imgedit-scale-height-' + postid);
		const fw = t.intval(w.val());
		const fh = t.intval(h.val());

		if (fw < 1) {
			w.focus();
			return false;
		}
		if (fh < 1) {
			h.focus();
			return false;
		}

		if (fw === t.hold.ow || fh === t.hold.oh) {
			return false;
		}

		data.do = 'scale';
		data.fwidth = fw;
		data.fheight = fh;
	} else if (action === 'restore') {
		data.do = 'restore';
	} else {
		return false;
	}

	t.toggleEditor(postid, 1);
	jQuery
		.post(ajaxurl, data, function (response) {
			jQuery('#image-editor-' + postid)
				.empty()
				.append(response.data.html);
			t.toggleEditor(postid, 0, true);
			// Refresh the attachment model so that changes propagate.
			if (t._view) {
				t._view.refresh();
			}
		})
		.done(function (response) {
			// Whether the executed action was `scale` or `restore`, the response does have a message.
			if (response && response.data.message.msg) {
				wp.a11y.speak(response.data.message.msg);
				return;
			}

			if (response && response.data.message.error) {
				wp.a11y.speak(response.data.message.error);
			}
		});

	return true;
};

/**
 * Recalculates the height or width and keeps the original aspect ratio.
 *
 * If the original image size is exceeded a red exclamation mark is shown.
 *
 * @since 2.9.0
 *
 * @memberof imageEdit
 *
 * @param {number}         postid The current post ID.
 * @param {number}         x      Is 0 when it applies the y-axis
 *                                and 1 when applicable for the x-axis.
 * @param {jQuery}         el     Element.
 *
 * @return {void}
 */
imageEdit.scaleChanged = function (postid, x, el) {
	let w1 = '';
	let h1 = '';

	const ratio =
		document.querySelector('input[name="wpmp-current-width-' + postid + '"]').value /
		document.querySelector('input[name="wpmp-current-height-' + postid + '"]').value;

	if (this.validateNumeric(el) === false) {
		return;
	}

	const w = jQuery('#imgedit-scale-width-' + postid);
	const h = jQuery('#imgedit-scale-height-' + postid);
	const warn = jQuery('#imgedit-scale-warn-' + postid);

	if (x) {
		h1 = w.val() !== '' ? Math.round(w.val() / ratio) : '';
		h.val(h1);
	} else {
		w1 = h.val() !== '' ? Math.round(h.val() * ratio) : '';
		w.val(w1);
	}

	if ((h1 && h1 > this.hold.oh) || (w1 && w1 > this.hold.ow)) {
		warn.css('visibility', 'visible');
	} else {
		warn.css('visibility', 'hidden');
	}
};

imageEdit.onEditModeChange = (value) => {
	const imageChanger = document.querySelector('.wpmp-image-size');
	if (value === 'individual') {
		imageChanger.classList.add('show');
	} else {
		imageEditInstance.openSize(null);
		imageChanger.classList.remove('show');
	}
};

imageEdit.onSizeChange = (size) => {
	imageEditInstance.openSize(size);
};
