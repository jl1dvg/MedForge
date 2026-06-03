import React from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import '../../css/solicitudes-v3.css';

const container = document.getElementById('solicitudes-v3-root');
if (container) {
  createRoot(container).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}
