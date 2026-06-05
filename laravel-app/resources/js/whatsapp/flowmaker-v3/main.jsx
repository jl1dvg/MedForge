import React from 'react';
import { createRoot } from 'react-dom/client';
import { FlowmakerV3App } from './FlowmakerV3App';
import '../../../css/flowmaker-v3.css';

const root = document.getElementById('flowmaker-v3-root');

if (root) {
    createRoot(root).render(<FlowmakerV3App />);
}
