import { HashRouter as Router, Route } from 'react-router-dom';
import { AppContextProvider } from './contexts/AppContext';
import App from './components/App';

const Root = () => {
	return (
		<AppContextProvider>
			<Router hashType="noslash">
				<Route exact path="/">
					<App />
				</Route>
				<Route path="/:folderSlug">
					<App />
				</Route>
			</Router>
		</AppContextProvider>
	);
};

export default Root;
