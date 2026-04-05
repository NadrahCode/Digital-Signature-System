document.addEventListener('DOMContentLoaded', function() {

    // ── SIDEBAR TOGGLE ──────────────────────────────────────────────
    const sidebarToggle   = document.getElementById('sidebarToggle');
    const mobileToggle    = document.getElementById('mobileMenuToggle');
    const sidebar         = document.querySelector('.sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 992 && sidebar) {
            if (!sidebar.contains(e.target) &&
                mobileToggle && !mobileToggle.contains(e.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        }
    });

    // ── DARK MODE ───────────────────────────────────────────────────
    const themeToggle = document.getElementById('themeToggle');
    const body        = document.body;

    // Apply saved theme immediately (overrides PHP hard-coded data-theme="light")
    const savedTheme = localStorage.getItem('theme') || 'light';
    body.setAttribute('data-theme', savedTheme);
    if (themeToggle) themeToggle.checked = (savedTheme === 'dark');

    if (themeToggle) {
        themeToggle.addEventListener('change', function() {
            const theme = this.checked ? 'dark' : 'light';
            body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        });
    }

    // ── ACTIVE SIDEBAR LINK ─────────────────────────────────────────
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });

    // ── TABLE SORTING ───────────────────────────────────────────────
    document.querySelectorAll('.sort-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tableId = this.getAttribute('data-table') + 'Table';
            const table   = document.getElementById(tableId);
            if (!table) return;

            table.querySelectorAll('th[data-sort]').forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    const tbody     = table.querySelector('tbody');
                    const rows      = Array.from(tbody.querySelectorAll('tr'));
                    const headers   = Array.from(table.querySelectorAll('th[data-sort]'));
                    const colIndex  = headers.indexOf(this);
                    const isAsc     = !this.classList.contains('asc');

                    headers.forEach(h => h.classList.remove('asc', 'desc'));
                    this.classList.add(isAsc ? 'asc' : 'desc');

                    rows.sort((a, b) => {
                        const aText = a.querySelectorAll('td')[colIndex]?.textContent.trim() || '';
                        const bText = b.querySelectorAll('td')[colIndex]?.textContent.trim() || '';
                        if (this.getAttribute('data-sort') === 'date') {
                            return isAsc ? new Date(aText) - new Date(bText)
                                         : new Date(bText) - new Date(aText);
                        }
                        return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
                    });

                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        });
    });

    // ── MOBILE RESIZE ───────────────────────────────────────────────
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 992 && sidebar) {
            sidebar.classList.remove('show');
        }
    });
});