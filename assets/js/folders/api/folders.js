import { post } from './client';

/**
 * Get folders.
 *
 * @return {*} Folders
 */
const getFolders = async () => {
	const response = await post({
		action: 'wpmp_get_folders',
	});

	if (response.status === 200) {
		const json = await response.json();

		return json.data;
	}

	return false;
};

/**
 * Create a folder
 *
 * @param {string} name Folder name
 * @param {string} parent Folder parent slug
 * @return {*} New folder
 */
const createFolder = async (name, parent) => {
	const response = await post({
		name,
		parent,
		action: 'wpmp_create_folder',
	});

	if (response.status === 200) {
		const json = await response.json();

		return json.data;
	}

	return false;
};

/**
 * Delete folder and maybe delete child media
 *
 * @param {number} id Folder ID
 * @param {boolean} deleteChildMedia Whether to delete child media or not
 * @return {*}
 */
const deleteFolder = async (id, deleteChildMedia) => {
	const response = await post({
		id,
		deleteChildMedia,
		action: 'wpmp_delete_folder',
	});

	if (response.status === 200) {
		const json = await response.json();

		return json.data;
	}

	return false;
};

/**
 * Rename folder
 *
 * @param {number} folderId Folder id
 * @param {string} name New folder name
 * @return {*} New folder
 */
const renameFolder = async (folderId, name) => {
	const response = await post({
		folderId,
		name,
		action: 'wpmp_rename_folder',
	});

	if (response.status === 200) {
		const json = await response.json();

		return json.data;
	}

	return false;
};

/**
 * Move image
 *
 * @param {string} imageId Image ID
 * @param {string} folderId Folder Id
 * @return {*} New folders
 */
const moveImage = async (imageId, folderId) => {
	const response = await post({
		imageId,
		folderId,
		action: 'wpmp_move_image',
	});

	if (response.status === 200) {
		const json = await response.json();

		return json.data;
	}

	return false;
};

export { getFolders, createFolder, deleteFolder, moveImage, renameFolder };
