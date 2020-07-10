import { AppContextProvider } from './contexts/AppContext';
import App from './components/App';

const Root = () => {
	return (
		<AppContextProvider>
			<App />
		</AppContextProvider>
	);
};

export default Root;
