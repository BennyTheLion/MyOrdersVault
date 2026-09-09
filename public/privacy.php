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
    .legal-doc .badge-readonly { display: inline-flex; align-items: center; gap: 8px; background: #ecfdf5; color: #065f46; border-radius: 10px; padding: 10px 16px; font-size: 0.85rem; margin: 6px 0 20px; }
</style>

<div class="legal-doc">
    <h1>מדיניות פרטיות</h1>
    <p class="legal-updated">עודכן לאחרונה: 09/09/2026</p>

    <div class="badge-readonly"><i class="fas fa-lock"></i> האפליקציה מבקשת הרשאת קריאה בלבד (read-only) לתיבת ה-Gmail שלך.</div>

    <h2>1. אילו הרשאות האפליקציה מבקשת</h2>
    <p>
        בעת ההתחברות עם Google, האפליקציה מבקשת גישת <strong>קריאה בלבד</strong> ל-Gmail
        (<code>gmail.readonly</code>) לצורך איתור מיילים של הזמנות וחשבוניות, ופרטי פרופיל
        בסיסיים (שם ותמונה) לצורך הצגתם בממשק. האפליקציה <strong>אינה</strong> מבקשת הרשאה
        לשלוח, למחוק או לשנות מיילים, ולעולם לא עושה זאת.
    </p>

    <h2>2. איזה מידע נשמר</h2>
    <ul>
        <li>נתוני ההזמנה שחולצו מהמייל: שם חנות, מספר הזמנה, סכום, מטבע ותאריך.</li>
        <li>מזהה ההודעה וה-thread ב-Gmail, כדי לאפשר קישור חזרה למייל המקורי.</li>
        <li>כתובת המייל וטוקן הגישה של Google, הנדרשים לביצוע הסנכרון.</li>
    </ul>
    <p>תוכן המיילים עצמם אינו נשמר במלואו - רק השדות הרלוונטיים המפורטים לעיל.</p>

    <h2>3. שימוש במידע</h2>
    <p>
        המידע משמש להצגת ההזמנות שלך בלוח הבקרה האישי בלבד. המידע <strong>לא</strong> משותף,
        נמכר או מועבר לצדדים שלישיים.
    </p>

    <h2>4. ביטול הגישה</h2>
    <p>
        ניתן לבטל את הגישה של האפליקציה לחשבון ה-Google בכל עת, דרך
        <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">הרשאות חשבון Google</a>.
        ביטול הגישה עוצר את הסנכרון; מידע שכבר נשמר באפליקציה ניתן למחיקה בבקשה ליצירת קשר (סעיף 6).
    </p>

    <h2>5. אבטחת מידע</h2>
    <p>הגישה לאפליקציה מוגנת בהתחברות דרך חשבון Google, וטוקני הגישה נשמרים בצד השרת ואינם חשופים למשתמש הקצה.</p>

    <h2>6. יצירת קשר ומחיקת מידע</h2>
    <p>
        לשאלות בנוגע לפרטיות או לבקשת מחיקת מידע, ניתן לפנות בוואטסאפ או בטלפון
        <a href="tel:0528529448">0528529448</a>.
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
