import { useState, useContext } from 'react';
import PropTypes from 'prop-types';
import { deleteFolder } from '../api/folders';
import { AppContext } from '../contexts/AppContext';

const { __ } = wp.i18n;

const DeleteFolder = ({ folder }) => {
	const [state, dispatch] = useContext(AppContext);
	const [deleteChildMedia, setDeleteChildMedia] = useState(false);

	const handleDelete = () => {
		dispatch({
			type: 'set_folder_deleting',
			payload: folder,
		});

		deleteFolder(folder.id, deleteChildMedia).then((delFolder) => {
			dispatch({
				type: 'delete_folder',
				payload: delFolder,
			});

			dispatch({
				type: 'set_folder_deleting',
				payload: null,
			});
		});
	};

	const handleShowDelete = () => {
		dispatch({
			type: 'set_show_folder_delete',
			payload: folder,
		});
	};

	const handleCancelDelete = () => {
		dispatch({
			type: 'set_show_folder_delete',
			payload: null,
		});
	};

	return (
		<>
			{(!state.showDeleteFolder || state.showDeleteFolder.id !== folder.id) &&
			(!state.showRenameFolder || state.showRenameFolder.id !== folder.id) ? (
				<button
					className={
						'delete-folder' +
						(state.showDeleteFolder && state.showDeleteFolder.id === folder.id
							? ' show-deleting'
							: '')
					}
					onClick={handleShowDelete}
					type="button"
					aria-label={__('Delete Folder', 'wpmp')}
				/>
			) : (
				''
			)}

			{state.showDeleteFolder && state.showDeleteFolder.id === folder.id ? (
				<div className="delete-confirm-folder">
					<input
						onClick={(event) => {
							setDeleteChildMedia(!!event.target.value);
						}}
						id={'delete-child-media-' + folder.id}
						type="checkbox"
					/>

					<label htmlFor={'delete-child-media-' + folder.id}>
						{__('Delete media items in this folder?', 'wpmp')}
					</label>
					<br />
					<button onClick={handleDelete} type="button" className="confirm-delete button">
						{__('Delete Folder', 'wpmp')}
					</button>
					<button
						onClick={handleCancelDelete}
						type="button"
						className="cancel-delete button cancel-button"
					>
						{__('Cancel', 'wpmp')}
					</button>
				</div>
			) : (
				''
			)}
		</>
	);
};

DeleteFolder.propTypes = {
	folder: PropTypes.object.isRequired,
};

export default DeleteFolder;
