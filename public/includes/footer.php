<footer class="footer">
    <div class="container">
        <p>&copy; 2025 My Orders Vault. כל הזכויות שמורות.</p>
        <div class="footer-links">
            <a href="/public/terms.php">תנאי שימוש</a>
            <span class="footer-sep">·</span>
            <a href="/public/privacy.php">מדיניות פרטיות</a>
            <span class="footer-sep">·</span>
            <a href="tel:0528529448">יש שאלות? 0528529448</a>
        </div>
    </div>
</footer>

<!-- Floating WhatsApp contact button -->
<a href="https://wa.me/972528529448?text=%D7%94%D7%99%D7%99%2C%20%D7%99%D7%A9%20%D7%9C%D7%99%20%D7%A9%D7%90%D7%9C%D7%94%20%D7%91%D7%A0%D7%95%D7%92%D7%A2%20%D7%9C-My%20Orders%20Vault"
   target="_blank" rel="noopener" class="fab-btn fab-whatsapp" title="יש שאלה? כתבו לנו בוואטסאפ - 0528529448" aria-label="צור קשר בוואטסאפ">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Floating accessibility widget -->
<div class="a11y-widget">
    <button type="button" class="fab-btn fab-a11y" id="a11yToggle" title="נגישות" aria-label="פתח תפריט נגישות" aria-expanded="false">
        <i class="fas fa-universal-access"></i>
    </button>
    <div class="a11y-panel" id="a11yPanel" hidden>
        <div class="a11y-panel-title">נגישות</div>
        <button type="button" class="a11y-option" id="a11yIncrease"><i class="fas fa-plus"></i> הגדל טקסט</button>
        <button type="button" class="a11y-option" id="a11yDecrease"><i class="fas fa-minus"></i> הקטן טקסט</button>
        <button type="button" class="a11y-option" id="a11yContrast"><i class="fas fa-adjust"></i> ניגודיות גבוהה</button>
        <button type="button" class="a11y-option" id="a11yUnderline"><i class="fas fa-underline"></i> הדגשת קישורים</button>
        <button type="button" class="a11y-option a11y-reset" id="a11yReset"><i class="fas fa-undo"></i> איפוס</button>
    </div>
</div>

<style>
    .footer-links { margin-top: 8px; font-size: 0.75rem; }
    .footer-links a { color: var(--gray-500); text-decoration: none; }
    .footer-links a:hover { color: var(--primary); text-decoration: underline; }
    .footer-sep { color: var(--gray-300); margin: 0 6px; }

    .fab-btn {
        position: fixed;
        width: 54px;
        height: 54px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        border: none;
        cursor: pointer;
        box-shadow: 0 6px 16px rgba(0,0,0,0.18);
        z-index: 9998;
        transition: transform 0.15s ease;
    }
    .fab-btn:hover { transform: scale(1.06); }

    /* right side - the sync indicator (header.css) occupies bottom-left */
    .fab-whatsapp { bottom: 30px; right: 30px; background: #25D366; }
    .fab-a11y { bottom: 100px; right: 30px; background: var(--primary, #3b82f6); position: fixed; }

    .a11y-widget { position: relative; }
    .a11y-panel {
        position: fixed;
        bottom: 165px;
        right: 30px;
        width: 220px;
        background: white;
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        padding: 12px;
        z-index: 9998;
    }
    .a11y-panel-title { font-weight: 700; font-size: 0.9rem; color: var(--gray-800, #1f2937); margin-bottom: 8px; text-align: center; }
    .a11y-option {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        background: var(--gray-50, #f9fafb);
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 6px;
        font-size: 0.82rem;
        color: var(--gray-700, #374151);
        cursor: pointer;
        text-align: right;
    }
    .a11y-option:hover { background: var(--gray-100, #f3f4f6); }
    .a11y-option.a11y-reset { color: var(--danger, #ef4444); margin-bottom: 0; }

    /* Accessibility states applied on <html> */
    html.a11y-font-lg { font-size: 112%; }
    html.a11y-font-xl { font-size: 126%; }
    html.a11y-contrast body { background: #fff !important; color: #000 !important; filter: contrast(1.15); }
    html.a11y-contrast .navbar,
    html.a11y-contrast .stats-card,
    html.a11y-contrast .table-container,
    html.a11y-contrast .card-modern { border: 2px solid #000 !important; }
    html.a11y-underline a { text-decoration: underline !important; }

    @media (max-width: 576px) {
        .fab-btn { width: 46px; height: 46px; font-size: 1.2rem; }
        .fab-a11y { bottom: 86px; }
        .a11y-panel { bottom: 140px; right: 16px; width: 190px; }
        .fab-whatsapp, .fab-a11y { right: 16px; }
    }
</style>

<script>
(function () {
    var root = document.documentElement;
    var FONT_CLASSES = ['a11y-font-lg', 'a11y-font-xl'];
    var STORAGE_KEY = 'a11yPrefs';

    function loadPrefs() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
        } catch (e) {
            return {};
        }
    }
    function savePrefs(prefs) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs)); } catch (e) {}
    }
    function applyPrefs(prefs) {
        root.classList.remove.apply(root.classList, FONT_CLASSES);
        if (prefs.font === 'lg') root.classList.add('a11y-font-lg');
        if (prefs.font === 'xl') root.classList.add('a11y-font-xl');
        root.classList.toggle('a11y-contrast', !!prefs.contrast);
        root.classList.toggle('a11y-underline', !!prefs.underline);
    }

    var prefs = loadPrefs();
    applyPrefs(prefs);

    var toggleBtn = document.getElementById('a11yToggle');
    var panel = document.getElementById('a11yPanel');

    toggleBtn.addEventListener('click', function () {
        var isHidden = panel.hasAttribute('hidden');
        if (isHidden) { panel.removeAttribute('hidden'); } else { panel.setAttribute('hidden', ''); }
        toggleBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    });

    document.getElementById('a11yIncrease').addEventListener('click', function () {
        prefs.font = prefs.font === 'lg' ? 'xl' : (prefs.font === 'xl' ? 'xl' : 'lg');
        applyPrefs(prefs); savePrefs(prefs);
    });
    document.getElementById('a11yDecrease').addEventListener('click', function () {
        prefs.font = prefs.font === 'xl' ? 'lg' : null;
        applyPrefs(prefs); savePrefs(prefs);
    });
    document.getElementById('a11yContrast').addEventListener('click', function () {
        prefs.contrast = !prefs.contrast;
        applyPrefs(prefs); savePrefs(prefs);
    });
    document.getElementById('a11yUnderline').addEventListener('click', function () {
        prefs.underline = !prefs.underline;
        applyPrefs(prefs); savePrefs(prefs);
    });
    document.getElementById('a11yReset').addEventListener('click', function () {
        prefs = {};
        applyPrefs(prefs); savePrefs(prefs);
    });

    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
            panel.setAttribute('hidden', '');
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
</body>
</html>
