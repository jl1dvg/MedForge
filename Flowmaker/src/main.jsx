import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './styles.css';

const rootElement = document.getElementById('flow');

if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<App />);
}
