const { wp, imageEdit, ajaxurl, jQuery } = window;

let imageEditInstance = null;
let nonceInstance = null;
let currentSize = null;

imageEdit.oldInit = imageEdit.init;
imageEdit.oldOpen = imageEdit.open;

imageEdit.init = function (postId) {
	this.oldInit(postId);

	setup();

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
			let max1;
			let max2;
			const parent = jQuery('#imgedit-crop-' + postid);
			const t = imageEdit;
			let historyObj;

			// Checks if there already is some image-edit history.
			if (event.data.history !== '') {
				historyObj = JSON.parse(event.data.history);
				// If last executed action in history is a crop action.
				if (historyObj[historyObj.length - 1].hasOwnProperty('c')) {
					/*
					 * A crop action has completed and the crop button gets disabled
					 * ensure the undo button is enabled.
					 */
					t.setDisabled(jQuery('#image-undo-' + postid), true);
					// Move focus to the undo button to avoid a focus loss.
					jQuery('#image-undo-' + postid).focus();
				}
			}

			parent.empty().append(img);

			// w, h are the new full size dimensions.
			max1 = Math.max(t.hold.w, t.hold.h);
			max2 = Math.max(jQuery(img).width(), jQuery(img).height());
			t.hold.sizer = max1 > max2 ? max2 / max1 : 1;

			t.initCrop(postid, img, parent);

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

			t.toggleEditor(postid, 0);
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
	const view = this._view;

	let dfd;
	let data;
	const elem = jQuery('#image-editor-' + postid);
	const head = jQuery('#media-head-' + postid);
	const btn = jQuery('#imgedit-open-btn-' + postid);
	const spin = btn.siblings('.spinner');

	currentSize = size;

	/*
	 * Instead of disabling the button, which causes a focus loss and makes screen
	 * readers announce "unavailable", return if the button was already clicked.
	 */
	if (btn.hasClass('button-activated')) {
		return;
	}

	spin.addClass('is-active');

	data = {
		action: 'image-editor',
		_ajax_nonce: nonceInstance,
		postid,
		do: 'open',
		size,
	};

	dfd = jQuery
		.ajax({
			url: ajaxurl,
			type: 'post',
			data,
			beforeSend() {
				btn.addClass('button-activated');
			},
		})
		.done(function (html) {
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

function setup() {
	const imageChanger = document.querySelector('.wpmp-image-size');

	imageChanger.addEventListener('change', (event) => {
		console.log(event);
		imageEditInstance.openSize(event.target.value);
	});

	const editModes = document.querySelectorAll('.edit-mode input[name="edit_type"]');
	editModes.forEach((editMode) => {
		editMode.addEventListener('change', (event) => {
			if (event.target.value === 'individual') {
				imageChanger.classList.add('show');
			} else {
				imageChanger.classList.remove('show');
			}
		});
	});
}
