let toastTimer = null;

export function showToast(message) {
    const toast = document.getElementById('toastNotice');
    if (!toast) return;
    document.getElementById('toastText').textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
}

export function initTabs() {
    const pages = { overview: 'pageOverview', packaging: 'pagePackaging' };

    document.querySelectorAll('[data-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const tab = button.dataset.tab;
            document.querySelectorAll('[data-tab]').forEach((b) => b.classList.toggle('active', b === button));
            Object.entries(pages).forEach(([name, id]) => document.getElementById(id)?.classList.toggle('active', name === tab));

            // Keep the tab when filters reload the page, and in the URL for sharing.
            const tabInput = document.getElementById('tabInput');
            if (tabInput) tabInput.value = tab;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState(null, '', url);
        });
    });
}

export function initClock() {
    const clock = document.getElementById('sysClock');
    if (!clock) return;
    const tick = () => {
        clock.textContent = `${new Date().toLocaleTimeString('id-ID', { hour12: false, timeZone: 'Asia/Jakarta' })} WIB`;
    };
    tick();
    setInterval(tick, 1000);
}

/**
 * Submit the parent form as soon as a filter or file input marked [data-auto-submit] changes.
 */
export function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach((input) => {
        input.addEventListener('change', () => {
            if (input.type === 'file') {
                if (!input.files.length) return;
                const label = document.getElementById('importLabel');
                if (label) label.textContent = 'Mengimpor…';
            }
            input.form?.requestSubmit();
        });
    });
}

export function initImageExport() {
    const button = document.querySelector('[data-action="export-image"]');
    if (!button) return;

    button.addEventListener('click', async () => {
        showToast('📸 Mengambil gambar screenshot HD… Mohon tunggu.');
        const { default: html2canvas } = await import('html2canvas');
        const exportButtons = document.querySelector('.export-actions');
        exportButtons.style.visibility = 'hidden';

        try {
            const canvas = await html2canvas(document.body, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#F7F5EF',
                logging: false,
                onclone: (clonedDoc) => {
                    // html2canvas cannot read Chart.js canvases reliably; swap them for snapshots.
                    const originals = document.querySelectorAll('canvas');
                    clonedDoc.querySelectorAll('canvas').forEach((clone, i) => {
                        const original = originals[i];
                        if (!original) return;
                        const img = clonedDoc.createElement('img');
                        img.src = original.toDataURL('image/png', 1.0);
                        img.style.width = `${original.offsetWidth || original.width}px`;
                        img.style.height = `${original.offsetHeight || original.height}px`;
                        img.style.display = 'block';
                        img.style.margin = '0 auto';
                        clone.parentNode?.replaceChild(img, clone);
                    });
                    clonedDoc.querySelector('.export-actions')?.style.setProperty('display', 'none');
                    clonedDoc.querySelector('#toastNotice')?.style.setProperty('display', 'none');
                },
            });

            const link = document.createElement('a');
            link.download = `FKS_Food_OEE_Dashboard_HD_${new Date().toISOString().split('T')[0]}.png`;
            link.href = canvas.toDataURL('image/png', 1.0);
            link.click();
            showToast('✨ Gambar dashboard berhasil di-download!');
        } catch (error) {
            console.error('Export image error:', error);
            showToast('⚠️ Gagal mengambil screenshot. Silakan coba lagi.');
        } finally {
            exportButtons.style.visibility = 'visible';
        }
    });
}
