import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import '../../css/pacientes-v2.css';

const root = document.getElementById('pac-root');
if (root) createRoot(root).render(<App />);
