<?php
/**
 * محرر نصوص غني بسيط قابل لإعادة الاستخدام. يتوقع: $name (اسم الحقل)،
 * $value (القيمة الحالية HTML)، و $id (معرّف فريد للعنصر داخل الصفحة).
 * التفاعل نفسه (execCommand + المزامنة مع textarea مخفي) مُعرَّف مرة واحدة
 * في assets/js/app.js عبر class="rte" العامة.
 */
?>
<div class="rte" data-target="<?= e($id) ?>">
    <div class="rte-toolbar">
        <button type="button" data-cmd="bold" title="عريض"><b>B</b></button>
        <button type="button" data-cmd="italic" title="مائل"><i>I</i></button>
        <button type="button" data-cmd="underline" title="تسطير"><u>U</u></button>
        <span class="rte-sep"></span>
        <button type="button" data-cmd="insertUnorderedList" title="قائمة نقطية">•≡</button>
        <button type="button" data-cmd="insertOrderedList" title="قائمة رقمية">1≡</button>
        <span class="rte-sep"></span>
        <button type="button" data-cmd="justifyRight" title="محاذاة يمين">⇥</button>
        <button type="button" data-cmd="justifyCenter" title="توسيط">↔</button>
        <button type="button" data-cmd="justifyLeft" title="محاذاة يسار">⇤</button>
        <span class="rte-sep"></span>
        <button type="button" data-cmd="formatBlock" data-value="h3" title="عنوان">H</button>
        <button type="button" data-cmd="removeFormat" title="مسح التنسيق">✕</button>
    </div>
    <div class="rte-content" contenteditable="true" dir="rtl"></div>
</div>
<textarea id="<?= e($id) ?>" name="<?= e($name) ?>" hidden><?= e($value ?? '') ?></textarea>
