import React, { useReducer, createContext } from 'react';
import PropTypes from 'prop-types';
import { getFlatFolderTree } from '../utils';

const AppContext = createContext();

const initialState = {
	folders: null,
	loadingFolders: false,
	currentFolder: null,
	deletingFolder: null,
	showDeleteFolder: null,
	showRenameFolder: null,
	renamingFolder: null,
};

const reducer = (state, action) => {
	let newFolders;
	let folderId;

	switch (action.type) {
		case 'create_folder':
			newFolders = state.folders.slice(0);
			newFolders.push(action.payload);

			return {
				...state,
				folders: getFlatFolderTree(newFolders),
			};
		case 'rename_folder':
			newFolders = state.folders.slice(0);

			for (let index = 0; index < newFolders.length; index += 1) {
				if (newFolders[index].id === action.payload.id) {
					newFolders[index].name = action.payload.name;
				}
			}

			return {
				...state,
				folders: newFolders,
			};
		case 'delete_folder':
			newFolders = state.folders.slice(0);
			newFolders = newFolders.filter((folder) => {
				if (action.payload.includes(folder.id)) {
					return false;
				}

				return true;
			});

			return {
				...state,
				folders: newFolders,
			};
		case 'get_folders':
		case 'move_image':
			return {
				...state,
				folders: getFlatFolderTree(action.payload),
			};
		case 'set_folders_loading':
			return {
				...state,
				loadingFolders: action.payload,
			};
		case 'set_folder_deleting':
			return {
				...state,
				deletingFolder: action.payload,
			};
		case 'set_show_folder_delete':
			return {
				...state,
				showDeleteFolder: action.payload,
			};
		case 'set_show_folder_rename':
			return {
				...state,
				showRenameFolder: action.payload,
			};
		case 'set_folder_renaming':
			return {
				...state,
				renamingFolder: action.payload,
			};
		case 'set_current_folder':
			folderId = action.payload ? action.payload.id : null;

			if (window.wpmpCurrentUploader) {
				const options = window.wpmpCurrentUploader.uploader.getOption();

				if (!options.multipart_params) {
					options.multipart_params = {};
				}

				if (!options.multipart_params.post_data) {
					options.multipart_params.post_data = {};
				}

				if (action.payload) {
					options.multipart_params.post_data.tax_input = {
						'wpmp-folder': [action.payload.id],
					};
				}
			}

			if (
				window.wpmpAttachmentBrowserCollection &&
				window.wpmpAttachmentBrowserCollection.props
			) {
				window.wpmpAttachmentBrowserCollection.props.set('wpmp-folder', folderId);

				// We do this to ensure the media query bypasses cache
				window.wpmpAttachmentBrowserCollection.props.set('wpmp-cache-bust', Date.now());
			}

			return {
				...state,
				currentFolder: action.payload,
			};
		default:
			throw new Error();
	}
};

const AppContextProvider = ({ children }) => {
	const [state, dispatch] = useReducer(reducer, initialState);

	return <AppContext.Provider value={[state, dispatch]}>{children}</AppContext.Provider>;
};

AppContextProvider.propTypes = {
	children: PropTypes.node.isRequired,
};

export { AppContextProvider, AppContext };
