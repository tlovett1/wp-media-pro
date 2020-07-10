import { wpmpFolders, ajaxurl } from 'window'; // eslint-disable-line import/no-unresolved

/**
 * Post request to AJAX endpoint
 *
 * @param {*} data Data to post
 * @param {*} endpoint AJAX endpoint
 * @return Promise
 */
function post(data, endpoint = ajaxurl) {
	data.nonce = wpmpFolders.nonce;

	const formData = new FormData();

	Object.keys(data).forEach((key) => {
		formData.append(key, data[key]);
	});

	const options = {
		method: 'POST',
		credentials: 'same-origin',
		body: formData,
	};

	return fetch(endpoint, options);
}

export { post };
