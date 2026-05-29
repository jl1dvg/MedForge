import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/crm-panel.css';
import App from './App';

const container = document.getElementById('crm-root');
if (container) {
  createRoot(container).render(<React.StrictMode><App /></React.StrictMode>);
}
