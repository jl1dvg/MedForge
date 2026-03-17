import '../../../../public/assets/vendor_components/OwlCarousel2/dist/assets/owl.carousel.css';
import '../../../../public/assets/vendor_components/OwlCarousel2/dist/assets/owl.theme.default.min.css';
import '../../css/v2/dashboard-home.css';
import { bootLegacyDashboardHomePage } from '../medforge/v2/bootLegacyDashboardHomePage';

bootLegacyDashboardHomePage('/js/pages/dashboard3.js').catch((error) => {
    console.error('Unable to initialize the main dashboard page bundle.', error);
});
