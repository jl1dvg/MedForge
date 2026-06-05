import React from 'react';
import { createRoot } from 'react-dom/client';

function FlowmakerV3BootPlaceholder() {
    return (
        <main className="fm-app">
            <header className="fm-topbar">
                <div className="fm-brand">Flowmaker V3</div>
                <a className="fm-btn" href="/v2/whatsapp/flowmaker">Volver a V2</a>
            </header>
            <section className="fm-loading">Cargando constructor visual...</section>
        </main>
    );
}

const root = document.getElementById('flowmaker-v3-root');

if (root) {
    createRoot(root).render(<FlowmakerV3BootPlaceholder />);
}
