(() => {
    'use strict';

    const modal = document.getElementById('searchItemModal');
    const form = document.getElementById('searchItemForm');
    if (!modal || !form) return;

    const fields = {
        id: document.getElementById('searchItemId'),
        title: document.getElementById('itemTitle'),
        url: document.getElementById('itemUrl'),
        icon: document.getElementById('itemIcon'),
        description: document.getElementById('itemDescription'),
        keywords: document.getElementById('itemKeywords'),
        synonyms: document.getElementById('itemSynonyms'),
        order: document.getElementById('itemOrder'),
        quick: document.getElementById('itemQuick'),
        fallback: document.getElementById('itemFallback'),
        active: document.getElementById('itemActive'),
    };
    const title = document.getElementById('searchModalTitle');
    let previousFocus = null;

    function openModal(item = null) {
        previousFocus = document.activeElement;
        fields.id.value = item?.id || 0;
        fields.title.value = item?.title || '';
        fields.url.value = item?.url || '';
        fields.icon.value = item?.icon || 'fas fa-search';
        fields.description.value = item?.description || '';
        fields.keywords.value = item?.keywords || '';
        fields.synonyms.value = item?.synonyms || '';
        fields.order.value = item?.sort_order || 100;
        fields.quick.checked = Number(item?.is_quick_link ?? 1) === 1;
        fields.fallback.checked = Number(item?.is_fallback ?? 0) === 1;
        fields.active.checked = Number(item?.is_active ?? 1) === 1;
        title.textContent = item ? 'Edit search destination' : 'Add search destination';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hs-modal-open');
        window.setTimeout(() => fields.title.focus(), 30);
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('hs-modal-open');
        if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
    }

    document.querySelectorAll('[data-search-item-add]').forEach((button) => button.addEventListener('click', () => openModal()));
    document.querySelectorAll('[data-search-item-edit]').forEach((button) => button.addEventListener('click', () => {
        try {
            openModal(JSON.parse(button.dataset.searchItemEdit));
        } catch (error) {
            window.alert('This destination could not be opened for editing.');
        }
    }));
    modal.querySelectorAll('[data-search-modal-close]').forEach((button) => button.addEventListener('click', closeModal));
    document.querySelectorAll('[data-search-delete-form]').forEach((deleteForm) => deleteForm.addEventListener('submit', (event) => {
        if (!window.confirm('Delete this destination? Existing search logs will remain.')) event.preventDefault();
    }));

    // Give administrators immediate feedback: same-site pasted URLs become root-relative paths.
    fields.url.addEventListener('blur', () => {
        const raw = fields.url.value.trim();
        if (!raw) return;
        try {
            const parsed = new URL(raw, window.location.origin);
            if ((parsed.protocol === 'http:' || parsed.protocol === 'https:') && parsed.origin === window.location.origin) {
                fields.url.value = (parsed.pathname || '/') + parsed.search;
            }
        } catch (error) {
            // Server-side validation remains authoritative for malformed or external URLs.
        }
    });

    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
        }
        if (event.key === 'Tab') {
            const focusable = [...modal.querySelectorAll('button, input, textarea')].filter((element) => !element.disabled);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    // Specialized history is intentionally opened one dataset at a time.
    const historyModal = document.getElementById('historyPopup');
    const historyBody = document.getElementById('historyPopupBody');
    const historyTitle = document.getElementById('historyPopupTitle');
    const historySource = document.querySelector('.hs-collapsible-body');
    let historyPreviousFocus = null;

    function openHistory(type, trigger) {
        if (!historyModal || !historyBody || !historySource) return;
        const historyGrid = historySource.querySelector('.hs-two');
        const panels = historyGrid ? Array.from(historyGrid.children) : [];
        const panel = panels[type === 'paper' ? 1 : 0];
        if (!panel) return;
        historyPreviousFocus = document.activeElement;
        historyBody.replaceChildren(panel.cloneNode(true));
        historyTitle.textContent = type === 'paper' ? 'Question-paper search history' : 'MCQ search history';
        historyModal.hidden = false;
        historyModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('hs-history-open');
        historyModal.querySelector('.hs-history-dialog [data-history-close]')?.focus();
        trigger?.setAttribute('aria-expanded', 'true');
    }

    function closeHistory() {
        if (!historyModal) return;
        historyModal.hidden = true;
        historyModal.setAttribute('aria-hidden', 'true');
        historyBody?.replaceChildren();
        document.body.classList.remove('hs-history-open');
        if (historyPreviousFocus && typeof historyPreviousFocus.focus === 'function') historyPreviousFocus.focus();
        document.querySelectorAll('[data-history-popup]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
    }

    document.querySelectorAll('[data-history-popup]').forEach((button) => {
        button.setAttribute('aria-expanded', 'false');
        button.addEventListener('click', () => openHistory(button.dataset.historyPopup, button));
    });
    historyModal?.querySelectorAll('[data-history-close]').forEach((button) => button.addEventListener('click', closeHistory));
    document.addEventListener('keydown', (event) => {
        if (!historyModal || historyModal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeHistory();
        }
    });
})();
