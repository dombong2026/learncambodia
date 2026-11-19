(() => {
    if (typeof window.qrcode !== 'function') return;

    for (const element of document.querySelectorAll('[data-certificate-qr]')) {
        const code = window.qrcode(0, 'M');
        code.addData(element.dataset.certificateQr);
        code.make();

        const svg = element.querySelector('svg');
        const modules = code.getModuleCount();
        const quietZone = 4;
        const size = modules + quietZone * 2;
        svg.setAttribute('viewBox', `0 0 ${size} ${size}`);

        let pixels = '';
        for (let row = 0; row < modules; row++) {
            for (let col = 0; col < modules; col++) {
                if (code.isDark(row, col)) {
                    pixels += `M${col + quietZone} ${row + quietZone}h1v1h-1z`;
                }
            }
        }

        const namespace = 'http://www.w3.org/2000/svg';
        const backdrop = document.createElementNS(namespace, 'rect');
        backdrop.setAttribute('width', size);
        backdrop.setAttribute('height', size);
        backdrop.setAttribute('fill', '#fff');
        const shape = document.createElementNS(namespace, 'path');
        shape.setAttribute('d', pixels);
        shape.setAttribute('fill', '#000');
        svg.append(backdrop, shape);
    }
})();
