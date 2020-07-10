import { useState, useContext } from 'react';
import PropTypes from 'prop-types';
import { renameFolder } from '../api/folders';
import { AppContext } from '../contexts/AppContext';

const { __ } = wp.i18n;

const RenameFolder = ({ folder }) => {
	const [state, dispatch] = useContext(AppContext);
	const [folderName, setFolderName] = useState(folder.name);

	const handleRename = () => {
		dispatch({
			type: 'set_folder_renaming',
			payload: folder,
		});

		renameFolder(folder.id, folderName).then((newFolder) => {
			dispatch({
				type: 'rename_folder',
				payload: newFolder,
			});

			dispatch({
				type: 'set_folder_renaming',
				payload: null,
			});

			dispatch({
				type: 'set_show_folder_rename',
				payload: null,
			});
		});
	};

	const maybeRename = (event) => {
		if (event.key === 'Enter') {
			handleRename(event);
		}
	};

	const handleShowRename = () => {
		dispatch({
			type: 'set_show_folder_rename',
			payload: folder,
		});
	};

	const handleCancelRename = () => {
		dispatch({
			type: 'set_show_folder_rename',
			payload: null,
		});
	};

	return (
		<>
			{state.showRenameFolder && state.showRenameFolder.id === folder.id ? (
				<div className="confirm-rename">
					<input
						type="text"
						value={folderName}
						className="rename-input"
						onKeyPress={maybeRename}
						onChange={(event) => {
							setFolderName(event.target.value);
						}}
					/>
					<br />
					<button type="button" className="rename button" onClick={handleRename}>
						{__('Rename Folder', 'wpmp')}
					</button>
					<button
						onClick={handleCancelRename}
						type="button"
						className="cancel-delete button cancel-button"
					>
						{__('Cancel', 'wpmp')}
					</button>
				</div>
			) : (
				''
			)}

			{(!state.showDeleteFolder || state.showDeleteFolder.id !== folder.id) &&
			(!state.showRenameFolder || state.showRenameFolder.id !== folder.id) ? (
				<button
					className="rename-folder"
					onClick={handleShowRename}
					type="button"
					aria-label={__('Rename Folder ' + folder.name, 'wpmp')}
				/>
			) : (
				''
			)}
		</>
	);
};

RenameFolder.propTypes = {
	folder: PropTypes.object.isRequired,
};

export default RenameFolder;
