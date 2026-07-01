import React from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/protocolos-v2.css';
import { ProtocolosApp } from './app';

const container = document.getElementById('protocolos-index-root');
if (container) {
  let config = {};
  try {
    config = JSON.parse(container.dataset.config || '{}');
  } catch (e) {
    console.error('protocolos-index: failed to parse config', e);
  }
  createRoot(container).render(
    <React.StrictMode>
      <ProtocolosApp config={config} />
    </React.StrictMode>
  );
}
