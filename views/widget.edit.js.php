<?php declare(strict_types = 0); ?>
window.widget_form = new class extends CWidgetForm {
    #form;
    init() {
        this._form = document.getElementById('widget-dialogue-form');
        this._multiselect = jQuery('#itemids_');
        this._container = document.getElementById('custom-names-container');
        this._hiddenField = document.getElementById('custom_names_json');
        
        // Add "Add Header" button
        const btnContainer = document.createElement('div');
        btnContainer.style = 'margin-bottom: 10px; display: flex; justify-content: flex-end;';
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.innerText = '+ Add Header';
        addBtn.className = 'btn-alt';
        addBtn.onclick = () => this.addHeaderRow();
        btnContainer.appendChild(addBtn);
        this._container.parentNode.insertBefore(btnContainer, this._container);

        this._multiselect.on('change', () => this.updateRows());
        
        jQuery(this._container).sortable({
            axis: 'y',              
            handle: '.js-drag-handle',
            containment: 'parent',  
            cursor: 'grabbing',
            tolerance: 'pointer',
            opacity: 0.6,
            update: () => {
                this.saveAll();
            }
        });
        
        this.updateRows();
        this.ready();
    }
    
    addHeaderRow(text = '') {
        const id = 'h_' + Date.now();
        this.renderRow({ id: id, name: '--- SECTION HEADER ---', is_header: true, config: { n: text } });
        this.saveAll();
    }

    saveAll() {
        const data = []; 
        const rows = this._container.querySelectorAll('.item-setting-row');
        rows.forEach(row => {
            const isHeader = row.dataset.isheader === 'true';
            const nameInput = row.querySelector('.custom-name-input');
            const maxInput = row.querySelector('.custom-max-input');
            const joinCheck = row.querySelector('.join-checkbox');
            const graphCheck = row.querySelector('.graph-checkbox');
            
            if (isHeader) {
                data.push({
                    i: row.dataset.itemid,
                    t: 'header',
                    n: nameInput.value.trim()
                });
            } else if (nameInput) {
                data.push({
                    i: row.dataset.itemid,
                    n: nameInput.value.trim(),
                    m: maxInput ? maxInput.value.trim() : '100',
                    j: joinCheck ? joinCheck.checked : false,
                    g: graphCheck ? graphCheck.checked : false
                });
            }
        });
        const jsonString = JSON.stringify(data);
        this._hiddenField.value = jsonString;
        
        if (this._debounceTimer) clearTimeout(this._debounceTimer);

        this._debounceTimer = setTimeout(() => {
            this._hiddenField.dispatchEvent(new Event('input', { bubbles: true }));
            this._hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
            jQuery(this._hiddenField).trigger('change');
        }, 500);
    }

    renderRow(itemData) {
        const id = itemData.id;
        const isHeader = itemData.is_header || false;
        const config = itemData.config || {};
        
        const row = document.createElement('div');
        row.className = 'item-setting-row';
        row.dataset.itemid = id;
        row.dataset.isheader = isHeader;
        row.style = 'margin-bottom: 8px; display: flex; align-items: center; gap: 10px; background: var(--color-bg); padding: 10px; border: 1px solid #444; border-radius: 4px;';
        
        if (isHeader) {
            row.style.background = 'rgba(255,255,255,0.05)';
            row.style.borderColor = 'rgba(255,255,255,0.2)';
        }

        const dragIcon = document.createElement('div');
        dragIcon.className = 'js-drag-handle';
        dragIcon.style = 'width: 24px; height: 24px; background: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 14 14\'%3E%3Cpath d=\'M0 3.5h14v2H0v-2zm0 5h14v2H0v-2z\' fill=\'%23888\'/%3E%3C/svg%3E") no-repeat center; background-size: 14px 14px; cursor: move; flex-shrink: 0; opacity: 0.6;';
        
        const nameSpan = document.createElement('span');
        nameSpan.innerText = isHeader ? 'SECTION HEADER' : itemData.name;
        nameSpan.style = 'flex: 1; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: bold; color: var(--color-font-main);';
        if (isHeader) nameSpan.style.color = '#999';

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.className = 'custom-name-input';
        nameInput.value = config.n || '';
        nameInput.placeholder = isHeader ? 'Header Text (e.g. METRICS)' : 'Label';
        nameInput.style = 'flex: 0 0 180px; width: 180px; font-size: 11px; height: 24px; padding: 0 5px;';
        if (isHeader) nameInput.style.flex = '0 0 250px';

        row.appendChild(dragIcon);
        row.appendChild(nameSpan);
        row.appendChild(nameInput);

        if (!isHeader) {
            const maxInput = document.createElement('input');
            maxInput.type = 'text';
            maxInput.className = 'custom-max-input';
            maxInput.value = config.m || '100';
            maxInput.style = 'flex: 0 0 60px; width: 60px; font-size: 11px; height: 24px; padding: 0 5px; text-align: center;';
            
            const joinCb = document.createElement('input');
            joinCb.type = 'checkbox';
            joinCb.className = 'join-checkbox checkbox-radio';
            joinCb.id = 'join_' + id;
            joinCb.checked = config.j || false;
            
            const joinLabel = document.createElement('label');
            joinLabel.htmlFor = 'join_' + id;
            joinLabel.style = 'font-size: 11px; cursor: pointer; color: var(--color-font-main); display: flex; align-items: center;';
            joinLabel.appendChild(document.createElement('span'));
            joinLabel.appendChild(document.createTextNode('Join?'));

            const graphCb = document.createElement('input');
            graphCb.type = 'checkbox';
            graphCb.className = 'graph-checkbox checkbox-radio';
            graphCb.id = 'graph_' + id;
            graphCb.checked = config.g || false;
            
            const graphLabel = document.createElement('label');
            graphLabel.htmlFor = 'graph_' + id;
            graphLabel.style = 'font-size: 11px; cursor: pointer; color: var(--color-font-main); display: flex; align-items: center;';
            graphLabel.appendChild(document.createElement('span'));
            graphLabel.appendChild(document.createTextNode('Graph?'));

            const updateMaxVisibility = () => {
                maxInput.style.display = (graphCb.checked && !joinCb.checked) ? 'block' : 'none';
            };

            row.appendChild(joinCb);
            row.appendChild(joinLabel);
            row.appendChild(graphCb);
            row.appendChild(graphLabel);
            row.appendChild(maxInput);

            updateMaxVisibility();
            
            joinCb.addEventListener('change', () => { updateMaxVisibility(); this.saveAll(); });
            graphCb.addEventListener('change', () => { updateMaxVisibility(); this.saveAll(); });
            maxInput.addEventListener('input', () => this.saveAll());
        } else {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerText = 'x';
            removeBtn.className = 'btn-link';
            removeBtn.style = 'color: #EF4444; font-weight: bold; margin-left: 10px;';
            removeBtn.onclick = () => { row.remove(); this.saveAll(); };
            row.appendChild(removeBtn);
        }

        nameInput.addEventListener('input', () => this.saveAll());
        this._container.appendChild(row);
    }

    updateRows() {
        const selectedItems = this._multiselect.multiSelect('getData');
        let savedItems = [];
        try {
            const rawValue = JSON.parse(this._hiddenField.value || '[]');
            savedItems = Array.isArray(rawValue) ? rawValue : [];
        } catch (e) { savedItems = []; }
        
        this._container.innerHTML = '';
        
        // Re-render based on saved order
        savedItems.forEach(saved => {
            if (saved.t === 'header') {
                this.renderRow({ id: saved.i, is_header: true, config: saved });
            } else {
                const item = selectedItems.find(x => x.id === saved.i);
                if (item) {
                    this.renderRow({ id: item.id, name: item.name, config: saved });
                }
            }
        });

        // Add any new items from multiselect that aren't in saved list
        selectedItems.forEach(item => {
            if (!savedItems.find(x => x.i === item.id)) {
                this.renderRow({ id: item.id, name: item.name });
            }
        });
        
        this.saveAll();
    }
};
