<?php

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\CalendarEvent;
use App\Core\Csrf;
use App\Core\ModuleManager;
use App\Core\Request;
use App\Core\View;

/**
 * تقويم موحّد يجمع كل التواريخ المحدَّدة من الإضافات المفعّلة الحالية والمستقبلية
 * (استحقاق مهمة، انتهاء صلاحية ملف، موعد اجتماع...) - ميزة أساسية بالنواة لا علاقة
 * لها بأي إضافة معيّنة، عبر modules/{key}/calendar.php الذي تُسجّله كل إضافة اختيارياً.
 * بالإضافة لأحداث خاصة بالشركة يضيفها مدير النظام/الشركة يدوياً (calendar_events).
 */
class CalendarController
{
    public function index(): void
    {
        $user = current_user();
        $companyId = $user['company_id'] ?? null;

        $month = Request::query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $daysInMonth = (int) date('t', strtotime($monthStart));

        // بداية الأسبوع السبت (الأحد=0..السبت=6 -> نحوّلها لترتيب يبدأ بالسبت)
        $firstWeekday = (int) date('w', strtotime($monthStart));
        $leadingBlanks = ($firstWeekday + 1) % 7;

        $events = ModuleManager::collectCalendarEvents($user, $monthStart, $monthEnd);

        $companyEvents = [];
        if ($companyId) {
            $companyEvents = CalendarEvent::forRange((int) $companyId, $monthStart, $monthEnd, Auth::id());
            foreach ($companyEvents as $ce) {
                $isPersonal = !empty($ce['user_id']);
                $events[] = [
                    'date' => $ce['event_date'],
                    'title' => ($isPersonal ? '👤 ' : '🏢 ') . $ce['title'],
                    'url' => route('/calendar?month=' . $month . '#event-' . $ce['id']),
                    'module' => $isPersonal ? 'personal' : 'company',
                    'id' => $ce['id'],
                ];
            }
        }

        $eventsByDay = [];
        foreach ($events as $event) {
            $eventsByDay[$event['date']][] = $event;
        }
        uksort($eventsByDay, 'strcmp');

        $prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

        View::render('calendar.index', [
            'pageTitle' => 'التقويم',
            'month' => $month,
            'monthLabel' => $this->monthLabel($monthStart),
            'daysInMonth' => $daysInMonth,
            'leadingBlanks' => $leadingBlanks,
            'eventsByDay' => $eventsByDay,
            'companyEvents' => $companyEvents,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'today' => date('Y-m-d'),
            'canManageEvents' => Auth::isSystemAdmin() || Auth::isCompanyAdmin(),
            'canAddEvents' => (bool) $companyId,
        ]);
    }

    /**
     * إضافة حدث تقويم: أي موظف يضيف حدثاً "شخصياً" يخصه وحده، بينما الحدث "العام
     * للشركة" (يظهر لكل الموظفين) محصور بمدير النظام/الشركة.
     */
    public function storeEvent(): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/calendar');
        }

        $companyId = Auth::companyId();
        if (!$companyId) {
            flash_set('error', 'حساب مدير النظام غير تابع لأي شركة، اختر شركة أولاً من صفحة الشركات.');
            redirect('/calendar');
        }

        $scope = Request::input('scope', 'personal');
        $isCompanyScope = $scope === 'company';
        if ($isCompanyScope && !Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }

        $title = trim((string) Request::input('title', ''));
        $eventDate = Request::input('event_date');
        $description = trim((string) Request::input('description', ''));

        if ($title === '' || !$eventDate) {
            flash_set('error', 'يرجى إدخال عنوان الحدث وتاريخه.');
            redirect('/calendar');
        }

        $eventId = CalendarEvent::create([
            'company_id' => $companyId,
            'user_id' => $isCompanyScope ? null : Auth::id(),
            'title' => $title,
            'description' => $description ?: null,
            'event_date' => $eventDate,
            'created_by' => Auth::id(),
            'send_reminder' => Request::input('send_reminder') ? 1 : 0,
        ]);

        ActivityLog::log('calendar.event_create', 'calendar_event', $eventId, ($isCompanyScope ? 'إضافة حدث شركة: ' : 'إضافة حدث شخصي: ') . $title);
        flash_set('success', $isCompanyScope ? 'تمت إضافة حدث الشركة - سيظهر لجميع الموظفين.' : 'تمت إضافة حدثك الشخصي.');
        redirect('/calendar?month=' . substr($eventDate, 0, 7));
    }

    public function destroyEvent(array $params): void
    {
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/calendar');
        }

        $event = CalendarEvent::find((int) $params['id']);
        $isManager = Auth::isSystemAdmin() || Auth::isCompanyAdmin();
        $isOwnPersonal = $event && !empty($event['user_id']) && (int) $event['user_id'] === (int) Auth::id();

        // حدث الشركة يحذفه المدراء، والحدث الشخصي يحذفه صاحبه (أو مدير شركته)
        $sameCompany = $event && (Auth::isSystemAdmin() || (int) $event['company_id'] === (int) Auth::companyId());
        if (!$event || !$sameCompany || (!$isManager && !$isOwnPersonal)) {
            flash_set('error', 'الحدث غير موجود.');
            redirect('/calendar');
        }

        CalendarEvent::delete($event['id']);
        ActivityLog::log('calendar.event_delete', 'calendar_event', $event['id'], "حذف حدث تقويم: {$event['title']}");
        flash_set('success', 'تم حذف الحدث.');
        redirect('/calendar?month=' . substr($event['event_date'], 0, 7));
    }

    private function monthLabel(string $monthStart): string
    {
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
            7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        $ts = strtotime($monthStart);
        return $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}
