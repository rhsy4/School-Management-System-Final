// ============================================================
// Нэгдсэн Цахим Сургуулийн Систем — JS
// ============================================================

function applyTheme(dark) {
    const html = document.documentElement;
    html.setAttribute('data-theme', dark ? 'dark' : '');
    
    // Update switch checkbox if exists
    const checkbox = document.getElementById('themeCheckbox');
    if (checkbox) checkbox.checked = dark;

    // Update old UI elements if they exist
    const icon = document.getElementById('darkIcon');
    const label = document.getElementById('darkLabel');
    if (icon) icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
    if (label) label.textContent = dark ? 'Light' : 'Dark';

    // Chart.js global defaults
    if (window.Chart) {
        Chart.defaults.color = dark ? '#94a3b8' : '#64748b';
        Chart.defaults.borderColor = dark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
    }
}

function toggleDarkMode() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const newDark = !isDark;
    localStorage.setItem('theme', newDark ? 'dark' : 'light');
    applyTheme(newDark);
}

// Initialize UI state on load
(function() {
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const shouldBeDark = saved === 'dark' || (!saved && prefersDark);
    applyTheme(shouldBeDark);

    // Listen for OS theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme')) {
            applyTheme(e.matches);
        }
    });
})();


// Modal нээх/хаах
function openModal(id) {
    document.getElementById(id).classList.add('open');
    // Scroll main content to top so modal is always visible
    var main = document.querySelector('.main-content');
    if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
// Overlay дарахад хаах
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// Flash мессеж автоматаар алга болгох (6 секунд + × товч header-д нэмэгдсэн)
const flash = document.querySelector('.flash');
if (flash) {
    // Scroll to top so flash is visible after redirect
    var main = document.querySelector('.main-content');
    if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
    
    setTimeout(() => {
        flash.style.transition = 'opacity .5s';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 600);
    }, 6000);
}

// Устгах баталгаажуулалт
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm || 'Устгах уу?')) e.preventDefault();
    });
});

// Таб дэлгэц
function showTab(tabId) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const pane = document.getElementById(tabId);
    if (pane) pane.classList.add('active');
    const btn = document.querySelector(`[data-tab="${tabId}"]`);
    if (btn) btn.classList.add('active');
}

// Хурдан хайлт (client-side table filter)
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('keyup', () => {
        const val = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
}

// Sidebar mobile toggle
document.querySelectorAll('.sidebar-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const sb = document.getElementById('sidebar');
        if (window.innerWidth <= 768) sb.classList.toggle('open');
        else sb.classList.toggle('collapsed');
    });
});

// Форм илгээх баталгаажуулалт
document.querySelectorAll('form[data-confirm-form]').forEach(form => {
    form.addEventListener('submit', e => {
        if (!confirm(form.getAttribute('data-confirm-form') || 'Хадгалах уу?')) {
            e.preventDefault();
        }
    });
});

// Форм submit үед loading indicator — давхар дарахаас сэргийлэх
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (btn && !btn.disabled && !btn.dataset.noSpinner) {
            btn.dataset.originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Түр хүлээнэ үү...';
            btn.disabled = true;
            // 10 секундын дараа сэргээх (алдаа гарвал)
            setTimeout(() => {
                btn.innerHTML = btn.dataset.originalText;
                btn.disabled = false;
            }, 10000);
        }
    });
});

// Excel татах
function exportTableToExcel(tableId, filename = '') {
    let table = document.getElementById(tableId);
    if (!table) return;
    let html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="UTF-8"></head>
        <body>${table.outerHTML}</body>
        </html>`;
    let blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = (filename || 'tailan') + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

