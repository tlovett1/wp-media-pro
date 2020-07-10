import { useState, useContext } from 'react';
import { createFolder } from '../api/folders';
import { AppContext } from '../contexts/AppContext';

const { __ } = wp.i18n;

const CreateFolder = () => {
	const [disabled, setDisabled] = useState(false);
	const [inputShowing, setInputShowing] = useState(false);
	const [folderName, setFolderName] = useState('');
	const [state, dispatch] = useContext(AppContext);

	const handleOpenInput = () => {
		setInputShowing(true);
	};

	const handleCreateFolder = (event) => {
		event.preventDefault();

		if (!folderName.length) {
			return;
		}

		if (disabled) {
			return;
		}

		let parent = null;
		if (state.currentFolder) {
			parent = state.currentFolder.id;
		}

		setDisabled(true);
		createFolder(folderName, parent).then((folder) => {
			dispatch({
				type: 'create_folder',
				payload: folder,
			});

			setDisabled(false);
			setInputShowing(false);
			setFolderName('');
		});
	};

	const maybeCreateFolder = (event) => {
		if (event.key === 'Enter') {
			handleCreateFolder(event);
		}
	};

	return (
		<div
			className={
				'add-folder-wrapper ' +
				(disabled ? ' disabled' : '') +
				(inputShowing ? ' input-showing' : '')
			}
		>
			{inputShowing ? (
				<>
					<input
						type="text"
						value={folderName}
						onChange={(e) => setFolderName(e.target.value)}
						onKeyPress={maybeCreateFolder}
						placeholder={__('New Folder Name', 'wpmp')}
					/>
					<button type="button" className="button" onClick={handleCreateFolder}>
						{__('Add', 'wpmp')}
					</button>
				</>
			) : (
				<button className="open-input" onClick={handleOpenInput} type="button">
					{__('Add Folder', 'wpmp')}
				</button>
			)}
		</div>
	);
};

export default CreateFolder;
