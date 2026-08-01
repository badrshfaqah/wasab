<?php
/**
 * الهيكل التنظيمي: شجرة متداخلة مبنية على "المدير المباشر".
 * $byId[id] = صف الموظف · $children[managerId] = [ids] · $roots = [ids الجذور]
 */
$seen = [];
$renderNode = function (int $id) use (&$renderNode, $byId, $children, &$seen): string {
    if (isset($seen[$id])) {
        return ''; // حماية من الحلقات (مدير يتبع مرؤوسه) - لا نُعيد رسم عقدة مرّت
    }
    $seen[$id] = true;
    $e = $byId[$id];
    $kids = $children[$id] ?? [];
    $sub = $e['job_title'] ?: '';
    if ($e['department']) {
        $sub .= ($sub ? ' · ' : '') . $e['department'];
    }
    $html = '<li>';
    $html .= '<a class="org-node" href="' . route('/employees/' . $id) . '">'
           . '<span class="org-name">' . e($e['full_name']) . '</span>'
           . ($sub !== '' ? '<span class="org-sub">' . e($sub) . '</span>' : '')
           . ($kids ? '<span class="org-count">' . count($kids) . ' مرؤوس</span>' : '')
           . '</a>';
    if ($kids) {
        $html .= '<ul>';
        foreach ($kids as $kid) {
            $html .= $renderNode($kid);
        }
        $html .= '</ul>';
    }
    return $html . '</li>';
};
?>
<div class="page-head">
    <div><h1>الهيكل التنظيمي</h1><p>شجرة الموظفين وفق المدير المباشر (<?= (int) $total ?> موظفاً نشطاً).</p></div>
    <a class="btn btn-outline" href="<?= route('/employees') ?>">↩ قائمة الموظفين</a>
</div>

<style>
.org-tree, .org-tree ul { list-style:none; margin:0; padding:0; }
.org-tree ul { padding-inline-start:22px; border-inline-start:2px solid var(--border); margin-inline-start:14px; margin-top:6px; }
.org-tree li { margin:6px 0; position:relative; }
.org-node {
    display:inline-flex; flex-direction:column; gap:2px; padding:8px 14px;
    background:var(--card,#fff); border:1px solid var(--border); border-radius:10px;
    text-decoration:none; color:inherit; min-width:180px; transition:border-color .15s;
}
.org-node:hover { border-color:var(--primary); }
.org-name { font-weight:700; }
.org-sub { font-size:12px; color:var(--muted); }
.org-count { font-size:11px; color:var(--primary); margin-top:2px; }
</style>

<div class="card">
    <?php if (!$roots): ?>
        <div class="empty-state"><div class="ic">🏢</div><h3>لا يوجد موظفون</h3><p>أضف موظفين وحدّد مديرهم المباشر لبناء الهيكل.</p></div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <ul class="org-tree">
                <?php
                foreach ($roots as $rootId) {
                    echo $renderNode($rootId);
                }
                // أي موظف لم تصله الشجرة (بسبب حلقة في المديرين) يُعرض كجذر إضافي حتى لا يختفي
                foreach (array_keys($byId) as $anyId) {
                    echo $renderNode((int) $anyId);
                }
                ?>
            </ul>
        </div>
        <p class="hint" style="margin-top:14px;">الموظفون بلا مدير محدَّد (أو مديرهم خارج القائمة) يظهرون في الأعلى كجذور. حدِّد "المدير المباشر" في الملف الوظيفي لضبط موقع كل موظف.</p>
    <?php endif; ?>
</div>
