const { wp, imageEdit, ajaxurl, jQuery } = window;

console.log('crops');

let imageEditInstance = null;
let nonceInstance = null;

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

imageEdit.openSize = function (size) {
	const { postid } = this;
	const view = this._view;

	let dfd;
	let data;
	const elem = jQuery('#image-editor-' + postid);
	const head = jQuery('#media-head-' + postid);
	const btn = jQuery('#imgedit-open-btn-' + postid);
	const spin = btn.siblings('.spinner');

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
}
