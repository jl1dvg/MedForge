import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/cirugias-index.css';
import App from './app';

const container = document.getElementById('cirugias-index-root');
if (container) {
  let config = {};
  try {
    config = JSON.parse(container.dataset.config || '{}');
  } catch (e) {
    console.error('cirugias-index: failed to parse config', e);
  }
  createRoot(container).render(
    <React.StrictMode>
      <App config={config} />
    </React.StrictMode>
  );
}
