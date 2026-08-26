<?php
/**
 * إزالة إضافة CRM: تُحذف جداولها فقط. دليل «جهات الاتصال» لا يُمس - الجهات
 * والأفراد ملك الشركة لا ملك CRM.
 */
return function (PDO $pdo): void {
    foreach ([
        'crm_logs', 'crm_list_items', 'crm_lists', 'crm_activities',
        'crm_opportunity_members', 'crm_opportunities', 'crm_stages', 'crm_pipelines',
        'crm_org_tags', 'crm_tags', 'crm_org_categories', 'crm_categories',
        'crm_workspace_orgs', 'crm_workspace_members', 'crm_workspaces',
    ] as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
};
