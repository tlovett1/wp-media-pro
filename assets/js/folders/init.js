import { wp, wpmpFolders, _ } from 'window'; // eslint-disable-line import/no-unresolved
import jQuery from 'jquery'; // eslint-disable-line import/no-unresolved
import ReactDom from 'react-dom';
import Root from './Root';

const { __ } = wp.i18n;

window.wpmpAttachmentBrowserCollection = null;

let dragImg;

/**
 * Prerender drag image
 *
 * @return {Node} Image node
 */
const getDragImage = () => {
	if (!dragImg) {
		dragImg = new Image();
		dragImg.src = wpmpFolders.pluginUrl + 'assets/img/img-icon.png';
	}

	return dragImg;
};

const currentAttachmentsBrowser = wp.media.view.AttachmentsBrowser;

wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
	createToolbar() {
		currentAttachmentsBrowser.prototype.createToolbar.apply(this);

		window.wpmpAttachmentBrowserCollection = this.collection;

		setTimeout(() => {
			const { parent } = this.views;
			const menus = parent.views.get('.media-frame-menu');
			if (menus && menus.length) {
				parent.$el.find('.media-menu').append('<div class="wpmp-folders-app-shell"></div>');

				ReactDom.render(<Root />, document.querySelector('.wpmp-folders-app-shell'));

				parent.$el.removeClass('hide-menu');
			}
		}, 50);
	},
});

wp.media.view.Attachment.Library = wp.media.view.Attachment.extend({
	buttons: {
		check: true,
	},

	/**
	 * Render view
	 *
	 * @return {wp.media.view.Attachment} Returns itself to allow chaining.
	 */
	render() {
		const options = _.defaults(
			this.model.toJSON(),
			{
				orientation: 'landscape',
				uploading: false,
				type: '',
				subtype: '',
				icon: '',
				filename: '',
				caption: '',
				title: '',
				dateFormatted: '',
				width: '',
				height: '',
				compat: false,
				alt: '',
				description: '',
			},
			this.options,
		);

		options.buttons = this.buttons;
		options.describe = this.controller.state().get('describe');

		if (options.type === 'image') {
			options.size = this.imageSize();
		}

		options.can = {};
		if (options.nonces) {
			options.can.remove = !!options.nonces.delete;
			options.can.save = !!options.nonces.update;
		}

		if (this.controller.state().get('allowLocalEdits')) {
			options.allowLocalEdits = true;
		}

		if (options.uploading && !options.percent) {
			options.percent = 0;
		}

		const img = getDragImage();

		this.el.draggable = true;
		this.el.ondragstart = (event) => {
			document.body.classList.add('wpmp-body-dragging');

			event.dataTransfer.setDragImage(img, 0, 0);
			event.dataTransfer.dropEffect = 'move';

			event.dataTransfer.setData(
				'application/wpmp-folder',
				event.target.getAttribute('data-id'),
			);
		};

		this.el.ondragend = () => {
			setTimeout(() => {
				document.body.classList.remove('wpmp-body-dragging');
			}, 50);
		};

		this.views.detach();
		this.$el.html(this.template(options));

		this.$el.toggleClass('uploading', options.uploading);

		if (options.uploading) {
			this.$bar = this.$('.media-progress-bar div');
		} else {
			delete this.$bar;
		}

		// Check if the model is selected.
		this.updateSelect();

		// Update the save status.
		this.updateSave();

		this.views.render();

		return this;
	},
});

wp.media.view.UploaderWindow = wp.media.view.UploaderWindow.extend({
	ready() {
		const postId = wp.media.view.settings.post.id;

		// If the uploader already exists, bail.
		if (this.uploader) {
			return;
		}

		if (postId) {
			this.options.uploader.params.post_id = postId;
		}
		this.uploader = new wp.Uploader(this.options.uploader);
		window.wpmpCurrentUploader = this.uploader;

		const { dropzone } = this.uploader;

		dropzone.on('dropzone:enter', _.bind(this.show, this));
		dropzone.on('dropzone:leave', _.bind(this.hide, this));

		jQuery(this.uploader).on('uploader:ready', _.bind(this._ready, this));
	},
});

const oldUploaderSuccess = wp.Uploader.prototype.success;
wp.Uploader.prototype.success = function success(attachment) {
	oldUploaderSuccess(attachment);

	if (window.wpmpCurrentUploader) {
		window.wpmpAttachmentBrowserCollection._requery(true);
		window.wpmpRefreshFolders();
	}
};

wp.media.view.UploaderInline = wp.media.view.UploaderInline.extend({
	prepare() {
		setTimeout(() => {
			if (!document.querySelector('body.wp-admin.upload-php')) {
				const { parent } = this.views;
				const menus = parent.views.get('.media-frame-menu');
				if (menus && menus.length) {
					parent.$el
						.find('.media-menu')
						.append('<div class="wpmp-folders-app-shell"></div>');

					ReactDom.render(<Root />, document.querySelector('.wpmp-folders-app-shell'));

					parent.$el.removeClass('hide-menu');
				}
			}
		}, 50);
	},
});

const currentAttachmentCompat = wp.media.view.AttachmentCompat;
wp.media.view.AttachmentCompat = wp.media.view.AttachmentCompat.extend({
	render() {
		currentAttachmentCompat.prototype.render.apply(this);

		if (this.renderedFolder) {
			return;
		}

		this.renderedFolder = true;

		const folderEl = document.createElement('span');
		folderEl.classList.add('setting');

		const folderLabel = document.createElement('span');
		folderLabel.classList.add('name');
		folderLabel.innerText = __('Folder', 'wpmp');

		const folderValue = document.createElement('span');
		folderValue.classList.add('value');
		folderValue.innerText = '';

		const { el } = this;

		jQuery
			.ajax({
				url: ajaxurl,
				method: 'post',
				data: {
					nonce: wpmpFolders.nonce,
					postId: this.model.attributes.id,
					action: 'wpmp_get_folder_path',
				},
			})
			.done((response) => {
				if (response.data) {
					folderValue.innerHTML = response.data;
				} else {
					folderValue.innerText = __('None', 'wpmp');
				}

				folderEl.appendChild(folderLabel);
				folderEl.appendChild(folderValue);

				el.prepend(folderEl);
			});
	},
});
