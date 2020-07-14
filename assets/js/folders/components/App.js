import { useContext, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { wpmpFolders } from 'window'; // eslint-disable-line import/no-unresolved
import CreateFolder from './CreateFolder';
import FolderList from './FolderList';
import { AppContext } from '../contexts/AppContext';
import { getObjectWhereKeyIs } from '../utils';

const { __ } = wp.i18n;

const App = () => {
	const { folderSlug } = useParams();
	const [state, dispatch] = useContext(AppContext);

	useEffect(() => {
		if (wpmpFolders.libraryPage) {
			if (!state.loadingFolders && state.folders && state.folders.length) {
				if (folderSlug) {
					const folder = getObjectWhereKeyIs(state.folders, 'slug', folderSlug);

					if (folder) {
						dispatch({
							type: 'set_current_folder',
							payload: folder,
						});
					}
				} else {
					dispatch({
						type: 'set_current_folder',
						payload: null,
					});
				}
			}
		}
	}, [folderSlug, state.loadingFolders, state.folders]);

	return (
		<div className="wpmp-folders-app">
			<h3>{__('Folders', 'wpmp')}</h3>

			<div className="nav">{!state.loadingFolders ? <CreateFolder /> : ''}</div>

			<FolderList />
		</div>
	);
};

export default App;
