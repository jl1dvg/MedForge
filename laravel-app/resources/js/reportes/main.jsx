import { createRoot } from 'react-dom/client';
import App from './app.jsx';
import './report.css';

const el = document.getElementById('reportes-unified-root');
if (el) {
    const config = JSON.parse(el.dataset.config || '{}');
    createRoot(el).render(<App config={config} />);
}
