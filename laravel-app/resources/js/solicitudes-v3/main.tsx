import React from 'react';
import { createRoot } from 'react-dom/client';
import type { AppConfig } from './types';
import { App } from './App';
import '../../css/solicitudes-v3.css';

const container = document.getElementById('solicitudes-v3-root');
if (container) {
  const config = JSON.parse(container.dataset.config ?? '{}') as AppConfig;
  createRoot(container).render(
    <React.StrictMode>
      <App config={config} />
    </React.StrictMode>
  );
}
