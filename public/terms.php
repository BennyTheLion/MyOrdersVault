<?php
require_once __DIR__ . '/../vendor/autoload.php';
use MyOrdersVault\Core\Session;

Session::start();
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .legal-doc { max-width: 760px; margin: 0 auto; padding: 40px 20px 70px; color: var(--gray-700); line-height: 1.8; }
    .legal-doc h1 { font-size: 1.8rem; font-weight: 700; color: var(--gray-800); margin-bottom: 6px; }
    .legal-doc .legal-updated { color: var(--gray-500); font-size: 0.85rem; margin-bottom: 32px; }
    .legal-doc h2 { font-size: 1.1rem; font-weight: 700; color: var(--gray-800); margin-top: 32px; margin-bottom: 10px; }
    .legal-doc p, .legal-doc li { font-size: 0.92rem; }
    .legal-doc ul { padding-right: 20px; margin-bottom: 0; }
    .legal-doc a { color: var(--primary); }
</style>

<div class="legal-doc">
    <h1>תנאי שימוש</h1>
    <p class="legal-updated">עודכן לאחרונה: 09/09/2026</p>

    <p>
        השימוש באפליקציית "My Orders Vault" ("האפליקציה") כפוף לתנאים המפורטים במסמך זה.
        השימוש באפליקציה מהווה הסכמה לתנאים אלה.
    </p>

    <h2>1. מטרת האפליקציה</h2>
    <p>
        האפליקציה נועדה לסייע למשתמש לאתר ולרכז במקום אחד מיילים של אישורי הזמנה, חשבוניות
        וקבלות שהתקבלו בתיבת ה-Gmail האישית שלו. האפליקציה היא כלי אישי לניהול הזמנות ואינה
        מהווה שירות חשבונאות, ייעוץ פיננסי או מסמך רשמי לצורכי מיסוי.
    </p>

    <h2>2. דיוק המידע</h2>
    <p>
        המידע המוצג באפליקציה (מספרי הזמנה, סכומים, שמות חנויות) מופק אוטומטית מתוך תוכן
        המיילים, ועלול להיות חלקי, שגוי או לא מעודכן. מומלץ לאמת פרטים קריטיים (כגון סכומי
        תשלום) מול המקור - המייל המקורי או אתר החנות.
    </p>

    <h2>3. אחריות המשתמש</h2>
    <ul>
        <li>המשתמש אחראי לשמירה על אבטחת חשבון ה-Google שלו.</li>
        <li>המשתמש רשאי לבטל את הגישה של האפליקציה לחשבונו בכל עת (ראו במדיניות הפרטיות).</li>
    </ul>

    <h2>4. הגבלת אחריות</h2>
    <p>
        האפליקציה מסופקת "as is", ללא כל אחריות מפורשת או משתמעת. בעל האפליקציה לא יהיה
        אחראי לכל נזק ישיר או עקיף שייגרם כתוצאה משימוש באפליקציה או הסתמכות על המידע המוצג בה.
    </p>

    <h2>5. שינויים בתנאים</h2>
    <p>תנאים אלה עשויים להתעדכן מעת לעת. המשך השימוש באפליקציה מהווה הסכמה לתנאים המעודכנים.</p>

    <h2>6. יצירת קשר</h2>
    <p>
        לשאלות בנוגע לתנאי השימוש ניתן לפנות בוואטסאפ או בטלפון
        <a href="tel:0528529448">0528529448</a>.
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
