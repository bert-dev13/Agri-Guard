// AGRIGUARD registration — barangay list from centralized API

import { initBarangaySelect } from '../shared/barangaySelect';

document.addEventListener('DOMContentLoaded', () => {
    initBarangaySelect(document.getElementById('farm_barangay_code'));
});
