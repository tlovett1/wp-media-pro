/**
 * Find object in an array with a key
 *
 * @param {Array} arr Array
 * @param {*} key Array key
 * @param {*} val Array value
 * @return {*} Object in array
 */
const getObjectWhereKeyIs = (arr, key, val) => {
	for (let i = 0; i < arr.length; i += 1) {
		if (arr[i][key] === val) {
			return arr[i];
		}
	}

	return null;
};

/**
 * Recurse through folders. Helper function
 *
 * @param {*} folder Folder
 * @param {*} folders All folders
 * @param {*} level Current level
 * @return {Array} Flattened folders
 */
const processFolder = (folder, folders, level) => {
	let newFolders = [];
	const newFolder = folder;
	newFolder.level = level;

	const newLevel = level + 1;

	newFolders.push(newFolder);

	for (let index = 0; index < folders.length; index += 1) {
		if (folders[index].parent === newFolder.id) {
			newFolders = newFolders.concat(processFolder(folders[index], folders, newLevel));
		}
	}

	return newFolders;
};

/**
 * Get flattened folder tree array
 *
 * @param {*} folders All folders
 * @return {Array} Folders
 */
const getFlatFolderTree = (folders) => {
	let newFolders = [];

	for (let index = 0; index < folders.length; index += 1) {
		if (folders[index].parent === 0) {
			newFolders = newFolders.concat(processFolder(folders[index], folders, 0));
		}
	}

	return newFolders;
};

export { getObjectWhereKeyIs, getFlatFolderTree };
