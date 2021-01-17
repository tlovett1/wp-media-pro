import jQuery from 'jquery'; // eslint-disable-line import/no-unresolved
import { wp, wpmpTaxonomies, ajaxurl } from 'window'; // eslint-disable-line import/no-unresolved

const { __ } = wp.i18n;

wp.media.view.AttachmentFilters.MediaTags = wp.media.view.AttachmentFilters.extend({
	tagName: 'select',
	createFilters() {
		const filters = {};

		wpmpTaxonomies['wpmp-media-tag_terms'].forEach((term) => {
			filters[term.term_id] = {
				text: term.name,
				props: {},
			};

			filters[term.term_id].props['wpmp-media-tag'] = term.slug;
		});

		filters.all = {
			text: __('All Media Tags', 'wpmp'),
			priority: 10,
			props: {},
		};

		filters.all.props['wpmp-media-tag'] = '';

		this.filters = filters;
	},
});

const currentAttachmentsBrowser = wp.media.view.AttachmentsBrowser;

wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
	createToolbar() {
		currentAttachmentsBrowser.prototype.createToolbar.apply(this);

		const taxFilter = new wp.media.view.AttachmentFilters.MediaTags({
			className: 'wpmp-tax-filter attachment-filters',
			controller: this.controller,
			model: this.collection.props,
			priority: -75,
		});

		this.toolbar.set('folder-filter', taxFilter);
		taxFilter.initialize();
	},
});

jQuery(document).ready(() => {
	wp.media.view.AttachmentCompat.prototype.on('ready', () => {
		// For some reason block editor doesn't work without the delay
		setTimeout(() => {
			const tagRow = document.querySelector('.compat-field-wpmp-media-tag');

			if (!tagRow) {
				return;
			}
			const tagField = tagRow.querySelector('input');
			tagField.value = '';

			const attachmentId = tagField.name.replace(/^attachments\[([0-9]+)\].*$/, '$1');

			if (!attachmentId) {
				return;
			}

			const addButton = document.createElement('input');
			addButton.type = 'button';
			addButton.classList.add('button', 'tagadd');
			addButton.value = 'Add';

			tagField.parentNode.appendChild(addButton);

			let tagList = null;

			function setupTagList() {
				if (tagList) {
					return;
				}

				tagList = document.createElement('ul');
				tagList.classList.add('tagchecklist');
				tagField.parentNode.appendChild(tagList);
			}

			function addTag(id, name) {
				setupTagList();

				const tag = document.createElement('li');
				tag.innerHTML =
					'<button data-term-id="' +
					id +
					'" type="button" class="ntdelbutton"><span class="remove-tag-icon" aria-hidden="true"></span><span class="screen-reader-text">Remove term: ' +
					name +
					'</span></button> &nbsp;' +
					name;
				tagList.appendChild(tag);
			}

			function handleAdd(event) {
				event.preventDefault();
				event.stopPropagation();

				tagField.classList.add('ui-autocomplete-loading');

				jQuery
					.ajax({
						url: ajaxurl,
						method: 'post',
						data: {
							nonce: wpmpTaxonomies.nonce,
							postId: attachmentId,
							term: tagField.value,
							taxonomy: 'wpmp-media-tag',
							action: 'wpmp_add_taxonomy_term',
						},
					})
					.done((response) => {
						addTag(response.data.term_id, response.data.name);

						tagField.value = '';
					})
					.always(() => {
						tagField.classList.remove('ui-autocomplete-loading');
					});

				return false;
			}

			jQuery(tagField).on('keyup change', (event) => {
				event.stopPropagation();
				event.preventDefault();

				if (event.keyCode === 13) {
					handleAdd(event);
				}

				return false;
			});

			addButton.onclick = handleAdd;

			jQuery(tagRow).on('click', '.ntdelbutton', (event) => {
				jQuery.ajax({
					url: ajaxurl,
					method: 'post',
					data: {
						nonce: wpmpTaxonomies.nonce,
						postId: attachmentId,
						termId: event.currentTarget.getAttribute('data-term-id'),
						taxonomy: 'wpmp-media-tag',
						action: 'wpmp_remove_taxonomy_term',
					},
				});

				event.currentTarget.parentNode.remove();
			});

			jQuery
				.ajax({
					url: ajaxurl,
					method: 'post',
					data: {
						nonce: wpmpTaxonomies.nonce,
						postId: attachmentId,
						taxonomy: 'wpmp-media-tag',
						action: 'wpmp_get_taxonomy_terms',
					},
				})
				.done((response) => {
					if (response.data.length) {
						response.data.forEach((term) => {
							addTag(term.term_id, term.name);
						});
					}
				});
		}, 300);
	});
});
