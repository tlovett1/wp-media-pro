import { useState, useContext } from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import { wpmpFolders } from 'window'; // eslint-disable-line import/no-unresolved
import { AppContext } from '../contexts/AppContext';
import DeleteFolder from './DeleteFolder';
import RenameFolder from './RenameFolder';
import { moveImage } from '../api/folders';

const { __ } = wp.i18n;

const FolderListItem = ({ folder }) => {
	const [state, dispatch] = useContext(AppContext);
	const [dragging, setDragging] = useState(false);
	const [moving, setMoving] = useState(false);

	const handleFolderChange = (newCurrentFolder) => {
		if (moving || dragging) {
			return;
		}

		dispatch({
			type: 'set_current_folder',
			payload: newCurrentFolder,
		});
	};

	const dragoverHandler = (event) => {
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';

		setDragging(true);
	};

	const dropHandler = (event) => {
		event.preventDefault();
		// Get the id of the target and add the moved element to the target's DOM
		const data = event.dataTransfer.getData('application/wpmp-folder');

		setMoving(true);
		setDragging(false);

		moveImage(data, folder.id).then((folders) => {
			setTimeout(() => {
				dispatch({
					type: 'move_image',
					payload: folders,
				});

				setMoving(false);
			}, 150);
		});

		setDragging(false);
	};

	const destination =
		state.currentFolder && state.currentFolder.id === folder.id ? '/' : '/#' + folder.slug;

	return (
		<li
			onDrop={dropHandler}
			onDragEnter={() => {
				setDragging(true);
			}}
			onDragLeave={() => {
				setDragging(false);
			}}
			onDragOver={dragoverHandler}
			className={
				'folder level-' +
				folder.level +
				(state.showDeleteFolder && state.showDeleteFolder.id === folder.id
					? ' show-deleting'
					: '') +
				(state.deletingFolder && state.deletingFolder.id === folder.id ? ' deleting' : '') +
				(state.renamingFolder && state.renamingFolder.id === folder.id ? ' renaming' : '') +
				(dragging ? ' dragging' : '') +
				(moving ? ' moving' : '') +
				(state.currentFolder && state.currentFolder.id === folder.id
					? ' current-folder'
					: '') +
				(state.showRenameFolder && state.showRenameFolder.id === folder.id
					? ' show-renaming'
					: '')
			}
		>
			{wpmpFolders.libraryPage ? (
				<Link
					className="toggle-folder"
					type="button"
					to={destination}
					aria-label={__('Open or Close Folder ' + folder.name)}
				>
					{folder.count >= 1 ? <span className="count">[{folder.count}]</span> : ''}
					{folder.name}
				</Link>
			) : (
				<button
					className="toggle-folder"
					type="button"
					onClick={() => {
						if (state.currentFolder && state.currentFolder.id === folder.id) {
							handleFolderChange(null);
						} else {
							handleFolderChange(folder);
						}
					}}
					aria-label={__('Open or Close Folder ' + folder.name)}
				>
					{folder.count >= 1 ? <span className="count">[{folder.count}]</span> : ''}
					{folder.name}
				</button>
			)}

			<RenameFolder folder={folder} />
			<DeleteFolder folder={folder} />
		</li>
	);
};

FolderListItem.propTypes = {
	folder: PropTypes.object.isRequired,
};

export default FolderListItem;
