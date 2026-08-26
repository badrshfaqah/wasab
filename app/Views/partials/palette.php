<?php
/**
 * لوحة الأوامر: نافذة واحدة تبحث في كل شيء - الشاشات، والسجلات (مستندات، جهات،
 * مهام، موظفين...) عبر مزودات البحث الموحّد، وأوامر سريعة تُنفَّذ مباشرة.
 * تُفتح بـ Ctrl/⌘+K من أي صفحة، وتُدار بالكامل من لوحة المفاتيح.
 */
use App\Core\Icons;
?>
<div class="palette" id="palette" hidden aria-hidden="true">
    <div class="palette-backdrop" data-palette-close></div>
    <div class="palette-box" role="dialog" aria-modal="true" aria-label="لوحة الأوامر">
        <div class="palette-input">
            <?= Icons::svg('search', 18) ?>
            <input type="text" id="palette-q" placeholder="ابحث عن شاشة أو جهة أو مستند… أو اكتب أمراً"
                   autocomplete="off" spellcheck="false">
            <kbd>Esc</kbd>
        </div>
        <div class="palette-results" id="palette-results"></div>
        <div class="palette-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> تنقّل</span>
            <span><kbd>Enter</kbd> فتح</span>
            <span><kbd>Ctrl</kbd>+<kbd>K</kbd> فتح اللوحة</span>
        </div>
    </div>
</div>
