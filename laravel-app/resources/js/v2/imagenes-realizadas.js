import { bootLegacyInlineDataTablePage } from '../medforge/v2/bootLegacyInlineDataTablePage';

bootLegacyInlineDataTablePage().catch((error) => {
    console.error('Unable to initialize the imagenes realizadas page bundle.', error);
});
