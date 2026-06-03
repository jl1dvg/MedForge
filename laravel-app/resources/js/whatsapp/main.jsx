/* MedForge · WhatsApp Chat v3 — Vite entry point */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { WaApp } from './app.jsx';

const container = document.getElementById('wa3-react-root');
if (container) {
  createRoot(container).render(<WaApp />);
}
