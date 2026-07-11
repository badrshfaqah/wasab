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
            $companyEvents = CalendarEvent::forRange((int) $companyId, $monthStart, $monthEnd);
            foreach ($companyEvents as $ce) {
                $events[] = [
                    'date' => $ce['event_date'],
                    'title' => '🏢 ' . $ce['title'],
                    'url' => route('/calendar?month=' . $month . '#event-' . $ce['id']),
                    'module' => 'company',
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
        ]);
    }

    /** إضافة حدث خاص بالشركة - محصور بمدير النظام/الشركة (نفس تصنيف باقي صفحات الإدارة الأساسية بالنواة). */
    public function storeEvent(): void
    {
        $this->guardManageEvents();
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/calendar');
        }

        $companyId = Auth::companyId();
        if (!$companyId) {
            flash_set('error', 'حساب مدير النظام غير تابع لأي شركة، اختر شركة أولاً من صفحة الشركات.');
            redirect('/calendar');
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
            'title' => $title,
            'description' => $description ?: null,
            'event_date' => $eventDate,
            'created_by' => Auth::id(),
            'send_reminder' => Request::input('send_reminder') ? 1 : 0,
        ]);

        ActivityLog::log('calendar.event_create', 'calendar_event', $eventId, "إضافة حدث تقويم: {$title}");
        flash_set('success', 'تمت إضافة الحدث.');
        redirect('/calendar?month=' . substr($eventDate, 0, 7));
    }

    public function destroyEvent(array $params): void
    {
        $this->guardManageEvents();
        if (!Csrf::verify(Request::input('_csrf'))) {
            flash_set('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
            redirect('/calendar');
        }

        $event = CalendarEvent::find((int) $params['id']);
        if (!$event || (!Auth::isSystemAdmin() && (int) $event['company_id'] !== Auth::companyId())) {
            flash_set('error', 'الحدث غير موجود.');
            redirect('/calendar');
        }

        CalendarEvent::delete($event['id']);
        ActivityLog::log('calendar.event_delete', 'calendar_event', $event['id'], "حذف حدث تقويم: {$event['title']}");
        flash_set('success', 'تم حذف الحدث.');
        redirect('/calendar?month=' . substr($event['event_date'], 0, 7));
    }

    private function guardManageEvents(): void
    {
        if (!Auth::isSystemAdmin() && !Auth::isCompanyAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], '');
            exit;
        }
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
