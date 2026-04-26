import './bootstrap';

import './public/navbar';
import './public/landing';
import './auth/login';
import './auth/register';
import './auth/verify-email';
import './auth/forgot-password';
import './user/user-navbar';
import './user/dashboard';
import './user/weather';
import './user/rainfall-trends';
import './user/crop-progress';
import './user/settings';
import './user/assistant';

import './admin/admin-navbar';
import './admin/dashboard';

if (document.getElementById('farm-map-page')) {
    import('./user/farm-map');
}

if (document.getElementById('structures-page')) {
    import('./user/structures');
}

if (document.querySelector('.admin-users-page')) {
    import('./admin/user-management');
}

if (document.querySelector('.admin-farms-page')) {
    import('./admin/farm-monitoring');
}

if (document.querySelector('.admin-analytics-page')) {
    import('./admin/analytics');
}
