import { initOverview } from './dashboard/overview';
import { initMetricModal } from './dashboard/metric-modal';
import { initPackaging } from './dashboard/packaging';
import { initAutoSubmit, initClock, initImageExport, initTabs } from './dashboard/ui';

initClock();
initAutoSubmit();

const payloadElement = document.getElementById('dashboard-payload');

if (payloadElement) {
    const payload = JSON.parse(payloadElement.textContent);

    initTabs();
    initOverview(payload);
    initMetricModal(payload);
    initPackaging(payload);
    initImageExport();
}
