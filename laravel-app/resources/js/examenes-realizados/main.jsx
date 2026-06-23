import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/examenes-realizados.css';
import App from './app';

const container = document.getElementById('examenes-realizados-root');
if (container) {
  let config = {};
  try {
    config = JSON.parse(container.dataset.config || '{}');
  } catch (e) {
    console.error('examenes-realizados: failed to parse config', e);
  }
  createRoot(container).render(
    <React.StrictMode>
      <App config={config} />
    </React.StrictMode>
  );
}
