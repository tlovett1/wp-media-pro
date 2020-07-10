import { useContext } from 'react';
import CreateFolder from './CreateFolder';
import FolderList from './FolderList';
import { AppContext } from '../contexts/AppContext';

const { __ } = wp.i18n;

const App = () => {
	const [state] = useContext(AppContext);

	return (
		<div className="wpmp-folders-app">
			<h3>{__('Folders', 'wpmp')}</h3>

			<div className="nav">{!state.loadingFolders ? <CreateFolder /> : ''}</div>

			<FolderList />
		</div>
	);
};

export default App;
