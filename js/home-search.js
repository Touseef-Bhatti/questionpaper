(() => {
    'use strict';

    const overlay = document.getElementById('homeSmartSearch');
    const input = document.getElementById('homeSearchInput');
    const results = document.getElementById('homeSearchResults');
    const status = document.getElementById('homeSearchStatus');
    const sectionTitle = document.getElementById('homeSearchSectionTitle');
    const emptyNotice = document.getElementById('homeSearchEmpty');
    if (!overlay || !input || !results) return;

    const searchBase = overlay.dataset.searchBase || '';
    const searchApiUrl = `${searchBase}api/home_search.php`;
    const maxQueryLength = 120;
    const initialMarkup = results.innerHTML;
    let activeIndex = -1;
    let debounceTimer = 0;
    let requestController = null;
    let previousFocus = null;
    let currentMatched = true;
    let lastSearchedQuery = '';

    const links = () => Array.from(results.querySelectorAll('.home-search-result'));

    function resolveSearchUrl(url) {
        const value = String(url || '');
        if (!value || value === '#' || /^(?:[a-z][a-z0-9+.-]*:|\/\/|\/|#)/i.test(value)) {
            return value || '#';
        }
        return `${searchBase}${value.replace(/^\/+/, '')}`;
    }

    function setActive(index) {
        const options = links();
        options.forEach((option) => option.classList.remove('is-active'));
        if (!options.length) {
            activeIndex = -1;
            return;
        }
        activeIndex = Math.max(0, Math.min(index, options.length - 1));
        options[activeIndex].classList.add('is-active');
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function openSearch() {
        previousFocus = document.activeElement;
        overlay.hidden = false;
        document.body.classList.add('home-search-open');
        window.setTimeout(() => input.focus(), 20);
    }

    function closeSearch() {
        overlay.hidden = true;
        document.body.classList.remove('home-search-open');
        input.value = '';
        results.innerHTML = initialMarkup;
        sectionTitle.textContent = 'Quick links';
        status.textContent = '';
        emptyNotice.hidden = true;
        activeIndex = -1;
        lastSearchedQuery = '';
        if (requestController) requestController.abort();
        if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
    }

    function createResult(item, kind) {
        if (Array.isArray(item.actions) && item.actions.length > 0) {
            const card = document.createElement('article');
            card.className = 'home-search-result home-search-group';
            card.setAttribute('role', 'option');
            card.dataset.searchItemId = String(item.id || 0);
            card.dataset.searchKind = kind;

            const heading = document.createElement('div');
            heading.className = 'home-search-group-heading';
            const iconWrap = document.createElement('span');
            iconWrap.className = 'home-search-result-icon';
            const icon = document.createElement('i');
            icon.className = /^[a-z0-9 -]+$/i.test(item.icon || '') ? item.icon : 'fas fa-search';
            iconWrap.append(icon);
            const copy = document.createElement('span');
            copy.className = 'home-search-group-copy';
            const title = document.createElement('strong');
            title.textContent = item.title || '';
            const description = document.createElement('small');
            description.textContent = item.description || '';
            copy.append(title, description);
            heading.append(iconWrap, copy);

            const meta = document.createElement('p');
            meta.className = 'home-search-group-meta';
            meta.textContent = item.meta || '';

            const actionList = document.createElement('div');
            actionList.className = 'home-search-actions';
            item.actions.forEach((action) => {
                const actionLink = document.createElement('a');
                actionLink.className = 'home-search-action';
                actionLink.href = resolveSearchUrl(action.url);
                const actionIcon = document.createElement('i');
                actionIcon.className = /^[a-z0-9 -]+$/i.test(action.icon || '') ? action.icon : 'fas fa-arrow-right';
                actionIcon.setAttribute('aria-hidden', 'true');
                actionLink.append(actionIcon, document.createTextNode(action.label || 'Open'));
                actionList.append(actionLink);
            });
            card.append(heading, meta, actionList);
            return card;
        }

        const anchor = document.createElement('a');
        anchor.className = 'home-search-result';
        anchor.href = resolveSearchUrl(item.url);
        anchor.setAttribute('role', 'option');
        anchor.dataset.searchItemId = String(item.id || 0);
        anchor.dataset.searchKind = kind;

        const iconWrap = document.createElement('span');
        iconWrap.className = 'home-search-result-icon';
        const icon = document.createElement('i');
        icon.className = /^[a-z0-9 -]+$/i.test(item.icon || '') ? item.icon : 'fas fa-search';
        iconWrap.append(icon);

        const copy = document.createElement('span');
        const title = document.createElement('strong');
        title.textContent = item.title;
        const description = document.createElement('small');
        description.textContent = item.description;
        copy.append(title, description);

        const arrow = document.createElement('i');
        arrow.className = 'fas fa-arrow-right home-search-result-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        anchor.append(iconWrap, copy, arrow);
        return anchor;
    }

    async function search(query) {
        query = query.slice(0, maxQueryLength);
        if (query.length < 2) return false;
        if (requestController) requestController.abort();
        requestController = new AbortController();
        status.textContent = 'Searching…';
        try {
            const response = await fetch(`${searchApiUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error('Search request failed');
            const data = await response.json();
            currentMatched = Boolean(data.matched);
            lastSearchedQuery = query;
            results.replaceChildren(...data.items.map((item) => createResult(item, currentMatched ? 'result' : 'fallback')));
            sectionTitle.textContent = currentMatched ? 'Best matches' : 'Popular destinations';
            status.textContent = currentMatched ? `${data.items.length} result${data.items.length === 1 ? '' : 's'}` : 'No exact match';
            emptyNotice.hidden = currentMatched;
            setActive(0);
            return true;
        } catch (error) {
            if (error.name === 'AbortError') return false;
            status.textContent = 'Search is temporarily unavailable';
            return false;
        }
    }

    function logSearch(anchor, overrideType) {
        const query = input.value.trim() || anchor.querySelector('strong')?.textContent || '';
        if (query.length < 2) return;
        const payload = {
            query,
            matched_item_id: Number(anchor.dataset.searchItemId) || null,
            match_type: overrideType || anchor.dataset.searchKind || (currentMatched ? 'result' : 'fallback'),
            results_count: links().length,
        };
        fetch(searchApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Home-Search-Token': overlay.dataset.searchToken || '' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {});
    }

    function logNoMatch() {
        const query = input.value.trim();
        if (query.length < 2) return;
        fetch(searchApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Home-Search-Token': overlay.dataset.searchToken || '' },
            body: JSON.stringify({ query, matched_item_id: null, match_type: 'no_match', results_count: 0 }),
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {});
    }

    document.querySelectorAll('[data-home-search-open]').forEach((button) => button.addEventListener('click', openSearch));
    overlay.querySelectorAll('[data-home-search-close]').forEach((button) => button.addEventListener('click', closeSearch));

    input.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        const query = input.value.trim();
        if (!query) {
            if (requestController) requestController.abort();
            results.innerHTML = initialMarkup;
            sectionTitle.textContent = 'Quick links';
            status.textContent = '';
            emptyNotice.hidden = true;
            activeIndex = -1;
            lastSearchedQuery = '';
            return;
        }
        if (query.length < 2) {
            if (requestController) requestController.abort();
            status.textContent = 'Keep typing…';
            return;
        }
        if (query.length > maxQueryLength) {
                query = query.slice(0, maxQueryLength);
                input.value = query;
            }
            debounceTimer = window.setTimeout(() => search(query), 180);
    });

    results.addEventListener('click', (event) => {
        const anchor = event.target.closest('.home-search-action, .home-search-result:not(.home-search-group)');
        if (anchor) logSearch(anchor);
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            overlay.hidden ? openSearch() : closeSearch();
            return;
        }
        if (overlay.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeSearch();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex <= 0 ? links().length - 1 : activeIndex - 1);
        } else if (event.key === 'Enter') {
            const query = input.value.trim();
            if (query && query !== lastSearchedQuery) {
                event.preventDefault();
                window.clearTimeout(debounceTimer);
                search(query).then((succeeded) => {
                    if (!succeeded) return;
                    const firstResult = links()[0];
                    if (firstResult) {
                        const destination = firstResult.classList.contains('home-search-group')
                            ? firstResult.querySelector('.home-search-action')
                            : firstResult;
                        if (destination) {
                            logSearch(destination);
                            window.location.assign(destination.href);
                        }
                    } else {
                        logNoMatch();
                    }
                });
                return;
            }
            const options = links();
            if (!options.length) return;
            event.preventDefault();
            const selected = options[activeIndex >= 0 ? activeIndex : 0];
            const destination = selected.classList.contains('home-search-group')
                ? selected.querySelector('.home-search-action')
                : selected;
            if (destination) {
                logSearch(destination);
                window.location.assign(destination.href);
            }
        } else if (event.key === 'Tab') {
            const focusable = [input, ...overlay.querySelectorAll('button, a[href]')].filter((element) => !element.hidden);
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

    if (new URLSearchParams(window.location.search).get('open_search') === '1' || window.location.hash === '#search') {
        window.history.replaceState({}, document.title, window.location.pathname);
        openSearch();
    }
})();
