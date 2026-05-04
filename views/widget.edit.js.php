<?php declare(strict_types = 0); ?>
window.widget_form = new class extends CWidgetForm {
    #form;
    init() {
        this._form = document.getElementById('widget-dialogue-form');
        this._multiselect = jQuery('#itemids_');
        this._container = document.getElementById('custom-names-container');
        this._hiddenField = document.getElementById('custom_names_json');
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
    
    saveAll() {
        const data = []; 
        const rows = this._container.querySelectorAll('.item-setting-row');
        rows.forEach(row => {
            const nameInput = row.querySelector('.custom-name-input');
            const widthSelect = row.querySelector('.width-select');
            const maxInput = row.querySelector('.input-max-value');
            const heightSelect = row.querySelector('.height-select');
            const graphCheck = row.querySelector('.graphs-checkbox');
            const binaryCheck = row.querySelector('.binary-checkbox');
            if (nameInput) {
                data.push({
                    i: row.dataset.itemid,
                    n: nameInput.value.trim(),
                    g: graphCheck ? graphCheck.checked : false,
                    b: binaryCheck ? binaryCheck.checked : false,
                    m: maxInput ? maxInput.value.trim() : '',
                    w: widthSelect ? parseInt(widthSelect.value) : 1,
                    h: heightSelect ? parseInt(heightSelect.value) : 1
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

    updateRows() {
        const selectedItems = this._multiselect.multiSelect('getData');
        let savedItems = [];
        try {
            const rawValue = JSON.parse(this._hiddenField.value || '[]');
            if (Array.isArray(rawValue)) {
                savedItems = rawValue;
            } else if (typeof rawValue === 'object' && rawValue !== null) {
                savedItems = Object.keys(rawValue).map(key => {
                    const obj = rawValue[key];
                    obj.id = key;
                    return obj;
                });
            }
        } catch (e) { savedItems = []; }
        
        selectedItems.sort((a, b) => {
            let indexA = savedItems.findIndex(x => (x.i || x.id) === a.id);
            let indexB = savedItems.findIndex(x => (x.i || x.id) === b.id);
            if (indexA === -1 && indexB === -1) return 0;
            if (indexA === -1) return 1;
            if (indexB === -1) return -1;
            return indexA - indexB;
        });
        
        this._container.innerHTML = '';
        selectedItems.forEach(item => {
            const id = item.id;
            const savedConfig = savedItems.find(x => (x.i || x.id) === id) || {};
            const config = {
                name: savedConfig.n || savedConfig.name || '',
                graphs: (savedConfig.g !== undefined) ? savedConfig.g : ((savedConfig.graphs !== undefined) ? savedConfig.graphs : false),
                binary: (savedConfig.b !== undefined) ? savedConfig.b : ((savedConfig.binary !== undefined) ? savedConfig.binary : false),
                width: savedConfig.w || savedConfig.width || 1,
                max_value: savedConfig.m || savedConfig.max_value || '',
                height: savedConfig.h || savedConfig.height || 1
            };

            const row = document.createElement('div');
            row.className = 'item-setting-row';
            row.dataset.itemid = id;
            row.style = 'margin-bottom: 5px; display: flex; align-items: center; gap: 8px; background: var(--color-bg); padding: 8px; border: 1px solid #c0c0c0; border-radius: 3px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);';
            
            const dragIcon = document.createElement('div');
            dragIcon.className = 'js-drag-handle';
            dragIcon.style = 'width: 24px; height: 24px; background: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 14 14\'%3E%3Cpath d=\'M0 3.5h14v2H0v-2zm0 5h14v2H0v-2z\' fill=\'%23666666\'/%3E%3C/svg%3E") no-repeat center; background-size: 14px 14px; cursor: move; flex-shrink: 0; opacity: 0.8; margin-right: 5px; border-radius: 3px;';
            
            const nameSpan = document.createElement('span');
            nameSpan.innerText = item.name;
            nameSpan.title = item.name;
            nameSpan.style = 'flex: 1; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: bold; color: #555;';

            const widthSelect = document.createElement('select');
            widthSelect.className = 'width-select';
            widthSelect.style = 'width: 65px; font-size: 11px;';
            
            const widthOptions = [
                {val: 10, text: '100%'}, {val: 9,  text: '90%'}, {val: 8,  text: '80%'}, {val: 7,  text: '70%'}, {val: 6,  text: '60%'},
                {val: 5,  text: '50%'}, {val: 4,  text: '40%'}, {val: 3,  text: '30%'}, {val: 2,  text: '20%'}, {val: 1,  text: '10%'}
            ];
            
            widthOptions.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.val;
                option.text = opt.text;
                if (parseInt(config.width) == opt.val) option.selected = true;
                widthSelect.appendChild(option);
            });

            const heightSelect = document.createElement('select');
            heightSelect.className = 'height-select';
            heightSelect.style = 'width: 65px; font-size: 11px; margin-right: 5px;';
            heightSelect.title = 'Height (rows)';
            const heightOptions = [
                {val: 10, text: '100%'}, {val: 9,  text: '90%'}, {val: 8,  text: '80%'}, {val: 7,  text: '70%'}, {val: 6,  text: '60%'},
                {val: 5,  text: '50%'}, {val: 4,  text: '40%'}, {val: 3,  text: '30%'}, {val: 2,  text: '20%'}, {val: 1,  text: '10%'}
            ];
            heightOptions.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.val;
                option.text = opt.text;
                if ((config.height || 1) == opt.val) option.selected = true;
                heightSelect.appendChild(option);
            });

            const cbWrapper = document.createElement('div');
            cbWrapper.style = 'display: flex; align-items: center; margin-right: 10px; flex-shrink: 0; min-width: 65px;'; 
            const cbId = `graphs_${id}`;
            const cbLabel = document.createElement('label');
            cbLabel.setAttribute('for', cbId);
            cbLabel.innerHTML = '<span></span>Graph?';
            cbLabel.style = 'cursor: pointer; font-size: 12px; margin-left: 3px; color: var(--color-text-primary);';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = cbId;
            checkbox.className = 'checkbox-radio graphs-checkbox';
            checkbox.checked = (config.graphs !== undefined) ? config.graphs : false;
            cbWrapper.appendChild(checkbox);
            cbWrapper.appendChild(cbLabel);

            const cbWrapper_up = document.createElement('div');
            cbWrapper_up.style = 'display: flex; align-items: center; margin-right: 10px; flex-shrink: 0; min-width: 65px;'; 
            const cbId_up = `graphs_up_${id}`;
            const checkbox_up = document.createElement('input');
            checkbox_up.type = 'checkbox';
            checkbox_up.id = cbId_up;
            checkbox_up.className = 'checkbox-radio binary-checkbox'; 
            checkbox_up.checked = (config.binary !== undefined) ? config.binary : false;
            const cbLabel_up = document.createElement('label');
            cbLabel_up.setAttribute('for', cbId_up);
            cbLabel_up.innerHTML = '<span></span>Up/Down?';
            cbLabel_up.style = 'cursor: pointer; font-size: 12px; margin-left: 3px; color: var(--color-text-primary);';
            cbWrapper_up.appendChild(checkbox_up);
            cbWrapper_up.appendChild(cbLabel_up);

            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.className = 'custom-name-input';
            nameInput.value = config.name || '';
            nameInput.placeholder = 'Label';
            nameInput.style = 'flex: 0 0 140px; width: 120px; font-size: 11px; position: relative; z-index: 10;';

            var maxInput = document.createElement('input');
            maxInput.type = 'text';
            maxInput.placeholder = 'Max';
            maxInput.style = 'flex: 0 0 140px; width: 40px; font-size: 11px; position: relative; z-index: 10;';
            maxInput.className = 'input-max-value';

            if (config.max_value) {
                maxInput.value = config.max_value;
            }

            var tdMax = document.createElement('div');
            tdMax.appendChild(maxInput);

            row.appendChild(dragIcon);
            row.appendChild(nameSpan);
            row.appendChild(widthSelect);
            row.appendChild(heightSelect);
            row.appendChild(cbWrapper);
            row.appendChild(cbWrapper_up);
            row.appendChild(tdMax);
            row.appendChild(nameInput);
            
            this._container.appendChild(row);

            const updateVisibility = () => {
                if (checkbox.checked) {
                    cbWrapper_up.style.display = 'flex';
                    tdMax.style.display = '';
                } else {
                    cbWrapper_up.style.display = 'none';
                    tdMax.style.display = 'none';
                }
            };
            updateVisibility();
            
            nameInput.addEventListener('input', () => this.saveAll());
            checkbox.addEventListener('change', () => {
                updateVisibility();
                this.saveAll();
            });
            widthSelect.addEventListener('change', () => this.saveAll());
            heightSelect.addEventListener('change', () => this.saveAll());
            checkbox_up.addEventListener('change', () => this.saveAll());
            maxInput.addEventListener('input', () => this.saveAll());
            this.saveAll();
        });
    }
};
