import { useContext, useEffect } from 'react';
import { getFolders } from '../api/folders';
import { AppContext } from '../contexts/AppContext';
import FolderListItem from './FolderListItem';

const { __ } = wp.i18n;

const FolderList = () => {
	const [state, dispatch] = useContext(AppContext);

	/**
	 * We call this outside the app
	 */
	window.wpmpRefreshFolders = () => {
		getFolders().then((folders) => {
			dispatch({
				type: 'get_folders',
				payload: folders,
			});
		});
	};

	useEffect(() => {
		if (state.folders === null && !state.loadingFolders) {
			dispatch({
				type: 'set_folders_loading',
				payload: true,
			});

			getFolders().then((folders) => {
				dispatch({
					type: 'set_folders_loading',
					payload: false,
				});

				dispatch({
					type: 'get_folders',
					payload: folders,
				});
			});
		}
	}, [state.folders, state.loadingFolders]); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<div className={'folders-list ' + (state.loadingFolders ? 'loading' : '')}>
			{!state.loadingFolders && state.folders && state.folders.length ? (
				<ul>
					{state.folders.map((folder) => {
						return <FolderListItem key={folder.id} folder={folder} />;
					})}
				</ul>
			) : (
				''
			)}

			{!state.loadingFolders && state.folders && !state.folders.length && (
				<>{__('None yet.', 'wpmp')}</>
			)}
		</div>
	);
};

export default FolderList;
