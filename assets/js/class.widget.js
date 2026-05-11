class NewWidgetZabbixItemGrid extends CWidget {

    _hideZabbixStyles() {
        const widgetRoot = this._target.closest('.dashboard-grid-widget');
        if (widgetRoot) {
            const header = widgetRoot.querySelector('.dashboard-grid-widget-head');
            if (header) {
                header.style.display = 'none';
            }
            widgetRoot.style.background = 'transparent';
            widgetRoot.style.boxShadow = 'none';
            widgetRoot.style.border = 'none';
            
            const contents = widgetRoot.querySelector('.dashboard-grid-widget-contents');
            if (contents) {
                contents.style.background = 'transparent';
            }
        }
    }

    _formatValue(value, units) {
        let val = parseFloat(value);
        if (isNaN(val)) return value + ' ' + units;

        const cleanUnits = units.trim();
        const byteUnits = ['B', 'Bps', 'b', 'bps', 'Bytes', 'bytes'];
        
        if (byteUnits.includes(cleanUnits) && Math.abs(val) >= 1024) {
            const prefixes = ['', 'K', 'M', 'G', 'T', 'P'];
            let power = 0;
            while (Math.abs(val) >= 1024 && power < prefixes.length - 1) {
                val /= 1024;
                power++;
            }
            return val.toFixed(1) + ' ' + prefixes[power] + cleanUnits;
        }

        // Handle uptime/seconds if needed
        if (cleanUnits === 's' || cleanUnits === 'uptime') {
            if (val > 86400) return (val / 86400).toFixed(1) + 'd';
            if (val > 3600) return (val / 3600).toFixed(1) + 'h';
            if (val > 60) return (val / 60).toFixed(1) + 'm';
            return val.toFixed(0) + 's';
        }

        return (val % 1 === 0 ? val : val.toFixed(1)) + ' ' + units;
    }

    setContents(response) {
        super.setContents(response);
        this._hideZabbixStyles();
        
        const svgs = this._body.querySelectorAll('.sparkline-svg');
        svgs.forEach(svg => {
            const crosshair = svg.querySelector('.sparkline-crosshair');
            const clocks = JSON.parse(svg.getAttribute('data-clocks'));
            const values = JSON.parse(svg.getAttribute('data-values'));
            const tsStart = parseInt(svg.getAttribute('data-ts-start'));
            const tsEnd = parseInt(svg.getAttribute('data-ts-end'));
            const duration = tsEnd - tsStart;
            const units = svg.getAttribute('data-units') || '';

            svg.onmousemove = (e) => {
                const rect = svg.getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const ratio = mouseX / rect.width;
                
                const targetTime = tsStart + (ratio * duration);
                
                let index = 0;
                let minDiff = Math.abs(clocks[0] - targetTime);
                for (let i = 1; i < clocks.length; i++) {
                    const diff = Math.abs(clocks[i] - targetTime);
                    if (diff < minDiff) {
                        minDiff = diff;
                        index = i;
                    }
                }
                
                const viewBoxWidth = 250; 
                const xInViewBox = ((clocks[index] - tsStart) / duration) * viewBoxWidth;  
                
                crosshair.setAttribute('x1', xInViewBox);
                crosshair.setAttribute('x2', xInViewBox);
                crosshair.style.display = 'block';
                
                const date = new Date(clocks[index] * 1000);
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const timeStr = `${day}.${month}, ${date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'})}`;

                const formattedVal = this._formatValue(values[index], units);
                const fullText = `${timeStr} | ${formattedVal}`;
                
                this._showTooltip(e, fullText);
            };

            svg.onmouseleave = () => {
                crosshair.style.display = 'none';
                this._hideTooltip();
            };
        });
    }

    _showTooltip(e, text) {
        let tooltip = document.getElementById('sparkline-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'sparkline-tooltip';
            tooltip.style = `
                position: fixed; 
                background: rgba(0, 0, 0, 0.9); 
                color: #ffffff; 
                padding: 6px 12px; 
                border-radius: 4px; 
                font-size: 11px; 
                z-index: 20000; 
                pointer-events: none; 
                border: 1px solid #444;
                white-space: nowrap;
                font-family: 'Inter', sans-serif;
                box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            `;
            document.body.appendChild(tooltip);
        }
        tooltip.innerText = text;
        tooltip.style.display = 'block';

        const offset = 15; 
        const tooltipWidth = tooltip.offsetWidth; 
        const windowWidth = document.documentElement.clientWidth; 

        let leftPos = e.clientX + offset;
        const topPos = e.clientY - 35;

        if (leftPos + tooltipWidth > windowWidth) {
            leftPos = e.clientX - tooltipWidth - offset;
        }

        tooltip.style.left = leftPos + 'px';
        tooltip.style.top = topPos + 'px';
    }

    _hideTooltip() {
        const tooltip = document.getElementById('sparkline-tooltip');
        if (tooltip) tooltip.style.display = 'none';
    }
}
