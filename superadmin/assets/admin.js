(() => {
    document.querySelectorAll('.bi').forEach(icon => icon.setAttribute('aria-hidden', 'true'));
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('sidebarScrim');
    const menu = document.querySelector('.mobile-menu-btn');
    const mobile = window.matchMedia('(max-width: 900px)');
    let activeModal = null;
    let returnFocus = null;
    const focusable = root => [...root.querySelectorAll('a[href],button,input,select,textarea,[tabindex="0"]')].filter(el => !el.disabled && el.getClientRects().length);
    const lockScroll = () => { document.body.style.overflow = activeModal || sidebar.classList.contains('open') ? 'hidden' : ''; };
    function toggleNavigation(open, restore = true) {
        sidebar.classList.toggle('open', open);
        sidebar.inert = mobile.matches && !open;
        scrim.hidden = !open;
        menu.setAttribute('aria-expanded', String(open));
        lockScroll();
        if (open) sidebar.querySelector('.sidebar-close').focus();
        else if (restore) menu.focus();
    }
    menu.addEventListener('click', () => toggleNavigation(!sidebar.classList.contains('open')));
    sidebar.querySelector('.sidebar-close').addEventListener('click', () => toggleNavigation(false));
    scrim.addEventListener('click', () => toggleNavigation(false));
    mobile.addEventListener('change', () => toggleNavigation(false, false));
    toggleNavigation(false, false);

    window.openModal = id => {
        const modal = document.getElementById(id);
        if (!modal) return;
        returnFocus = document.activeElement;
        activeModal = modal;
        modal.style.display = 'flex';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        const title = modal.querySelector('.modal-title');
        if (title) {
            title.id ||= id + 'Title';
            modal.setAttribute('aria-labelledby', title.id);
        }
        lockScroll();
        (modal.querySelector('input:not([type=hidden]), select') || focusable(modal)[0] || modal).focus();
    };
    window.closeModal = id => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.style.display = 'none';
        activeModal = null;
        lockScroll();
        returnFocus?.focus();
    };
    document.querySelectorAll('.modal-overlay, .modal-backdrop-custom').forEach(modal => {
        modal.addEventListener('click', event => { if (event.target === modal) closeModal(modal.id); });
    });
    document.querySelectorAll('.modal-close').forEach(button => {
        button.type = 'button';
        button.setAttribute('aria-label', 'Close dialog');
        button.title = 'Close dialog';
    });
    document.addEventListener('keydown', event => {
        const root = activeModal || (sidebar.classList.contains('open') ? sidebar : null);
        if (!root) return;
        if (event.key === 'Escape') {
            activeModal ? closeModal(activeModal.id) : toggleNavigation(false);
        }
        if (event.key === 'Tab') {
            const items = focusable(root);
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    document.querySelectorAll('.page-body table').forEach(table => {
        table.querySelectorAll('th').forEach(th => th.setAttribute('scope', 'col'));
    });
    document.querySelectorAll('form[method="GET"]').forEach(form => form.classList.add('filter-form'));
    document.querySelectorAll('input:not([type=hidden]),select,textarea').forEach((input, index) => {
        input.id ||= 'admin-field-' + index;
        if (input.labels.length) return;
        const label = input.previousElementSibling;
        if (label?.matches('label,.input-label,.form-label-custom')) {
            if (label.tagName === 'LABEL') label.htmlFor = input.id;
            else { label.id ||= input.id + '-label'; input.setAttribute('aria-labelledby', label.id); }
        } else input.setAttribute('aria-label', input.placeholder || input.name.replaceAll('_', ' '));
    });
    document.querySelectorAll('.sub-row').forEach(row => {
        ['Shop', 'Plan', 'Status', 'Subscription period', 'Payment reference', 'Commission', 'Actions'].forEach((label, index) => {
            const field = row.children[index];
            if (!field || index === 6) return;
            const caption = document.createElement('span');
            caption.className = 'sub-field-label';
            caption.textContent = label;
            field.prepend(caption);
        });
    });
    document.querySelectorAll('a[title],button[title]').forEach(control => {
        if (!control.textContent.trim()) control.setAttribute('aria-label', control.title);
    });
    document.querySelectorAll('button, a').forEach(control => {
        if (control.textContent.trim() || control.hasAttribute('aria-label')) return;
        const icon = control.querySelector('.bi');
        const labels = { 'bi-search': 'Search', 'bi-box-arrow-up-right': 'Open shop', 'bi-eye': 'Preview', 'bi-trash3': 'Delete' };
        const label = Object.entries(labels).find(([name]) => icon?.classList.contains(name))?.[1];
        if (label) { control.setAttribute('aria-label', label); control.title ||= label; }
    });
})();
