document.addEventListener('DOMContentLoaded', function(){
    try {
        const defaults = [10,25,50,100,'All'];
        const tables = Array.from(document.querySelectorAll('table'));
        tables.forEach(function(table, idx){
            // Skip very small tables or those opting out
            if (table.dataset.disablePag === '1') return;
            const tbody = table.tBodies && table.tBodies[0];
            if (!tbody) return;
            const rows = Array.from(tbody.rows || []);
            if (rows.length <= 10) return; // don't show selector for tiny tables

            const key = 'pag_len:' + (location.pathname || '') + ':' + (table.id || idx);
            const stored = sessionStorage.getItem(key);
            const current = stored || '10';

            // Build selector container
            const wrapper = document.createElement('div');
            wrapper.style.display = 'flex';
            wrapper.style.justifyContent = 'flex-end';
            wrapper.style.margin = '8px 0';
            wrapper.className = 'paglen-wrapper';

            const label = document.createElement('label');
            label.style.marginRight = '8px';
            label.style.fontSize = '13px';
            label.style.color = '#333';
            label.textContent = 'Per page:';

            const select = document.createElement('select');
            select.style.padding = '6px';
            select.style.border = '1px solid #ddd';
            select.style.borderRadius = '4px';
            select.style.fontSize = '13px';

            defaults.forEach(function(opt){
                const o = document.createElement('option');
                o.value = opt === 'All' ? 'all' : String(opt);
                o.textContent = String(opt);
                if (String(opt) === String(current) || (opt === 'All' && current === 'all')) o.selected = true;
                select.appendChild(o);
            });

            select.addEventListener('change', function(e){
                const v = e.target.value;
                sessionStorage.setItem(key, v);
                apply(v);
            });

            wrapper.appendChild(label);
            wrapper.appendChild(select);

            // insert wrapper before table
            table.parentNode.insertBefore(wrapper, table);

            function apply(v){
                if (v === 'all'){
                    rows.forEach(r => r.style.display = '');
                } else {
                    const n = parseInt(v, 10) || 10;
                    rows.forEach(function(r, i){ r.style.display = (i < n) ? '' : 'none'; });
                }
            }

            // initial apply
            apply(current === 'all' ? 'all' : current);
        });
    } catch (err){
        // Fail silently but log to console for debugging
        console && console.error && console.error('Pagination-length init error', err);
    }
});