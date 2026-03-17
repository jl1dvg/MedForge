const loadShell = async () => {
    await import('./medforge/legacy/global-search.js');
    await import('./medforge/legacy/template.js');
};

loadShell().catch((error) => {
    console.error('Unable to initialize MedForge shell bundle.', error);
});
