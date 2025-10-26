(() => {
    const clockElement = document.querySelector('[data-time-display]');
    const settingsButton = document.querySelector('.menu-bar__settings');
    const dropdown = document.querySelector('[data-dropdown]');

    function updateClock() {
        if (!clockElement) {
            return;
        }

        const now = new Date();
        const formatter = new Intl.DateTimeFormat('de-DE', {
            hour: '2-digit',
            minute: '2-digit'
        });

        clockElement.textContent = formatter.format(now);
        clockElement.dateTime = now.toISOString();
    }

    function scheduleClockUpdates() {
        updateClock();

        const now = new Date();
        const msToNextMinute = (60 - now.getSeconds()) * 1000 - now.getMilliseconds();

        window.setTimeout(() => {
            updateClock();
            window.setInterval(updateClock, 60 * 1000);
        }, Math.max(msToNextMinute, 0));
    }

    function closeDropdown() {
        if (!settingsButton || !dropdown) {
            return;
        }

        dropdown.classList.remove('is-open');
        settingsButton.setAttribute('aria-expanded', 'false');
    }

    function openDropdown() {
        if (!settingsButton || !dropdown) {
            return;
        }

        dropdown.classList.add('is-open');
        settingsButton.setAttribute('aria-expanded', 'true');
    }

    function toggleDropdown() {
        if (!settingsButton || !dropdown) {
            return;
        }

        if (dropdown.classList.contains('is-open')) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    function handleDocumentClick(event) {
        if (!settingsButton || !dropdown) {
            return;
        }

        const target = event.target;
        if (target instanceof Node && (dropdown.contains(target) || settingsButton.contains(target))) {
            return;
        }

        closeDropdown();
    }

    function handleEscape(event) {
        if (event.key === 'Escape') {
            closeDropdown();
        }
    }

    function filterModsets(query) {
        const normalizedQuery = query.trim().toLowerCase();
        const sections = document.querySelectorAll('.modset-section');
        const emptyState = document.querySelector('[data-search-empty]');
        let matches = 0;

        sections.forEach((section) => {
            const cards = section.querySelectorAll('.modset-card');
            let sectionHasMatch = false;

            cards.forEach((card) => {
                const searchText = (card.dataset.searchText || card.textContent || '').toLowerCase();
                const isMatch = normalizedQuery === '' || searchText.includes(normalizedQuery);

                card.classList.toggle('is-hidden', !isMatch);

                if (isMatch) {
                    sectionHasMatch = true;
                    matches += 1;
                }
            });

            section.classList.toggle('is-hidden', !sectionHasMatch);
        });

        if (emptyState) {
            emptyState.hidden = normalizedQuery === '' || matches > 0;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        scheduleClockUpdates();

        if (settingsButton) {
            settingsButton.addEventListener('click', (event) => {
                event.preventDefault();
                toggleDropdown();
            });
        }

        if (dropdown) {
            dropdown.addEventListener('click', (event) => {
                const target = event.target;
                if (target instanceof HTMLAnchorElement) {
                    closeDropdown();
                }
            });
        }

        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleEscape);

        const searchInput = document.querySelector('[data-search-input]');
        if (searchInput) {
            const searchForm = searchInput.closest('form');
            if (searchForm) {
                searchForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                });
            }

            searchInput.addEventListener('input', () => {
                filterModsets(searchInput.value);
            });
        }
    });
})();
