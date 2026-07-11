<?php

namespace App\Controllers;

use App\Core\ModuleManager;
use App\Core\Request;
use App\Core\View;

/**
 * تقويم موحّد يجمع كل التواريخ المحدَّدة من الإضافات المفعّلة الحالية والمستقبلية
 * (استحقاق مهمة، انتهاء صلاحية ملف، موعد اجتماع...) - ميزة أساسية بالنواة لا علاقة
 * لها بأي إضافة معيّنة، عبر modules/{key}/calendar.php الذي تُسجّله كل إضافة اختيارياً.
 */
class CalendarController
{
    public function index(): void
    {
        $user = current_user();

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
        $eventsByDay = [];
        foreach ($events as $event) {
            $eventsByDay[$event['date']][] = $event;
        }

        $prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

        View::render('calendar.index', [
            'pageTitle' => 'التقويم',
            'month' => $month,
            'monthLabel' => $this->monthLabel($monthStart),
            'daysInMonth' => $daysInMonth,
            'leadingBlanks' => $leadingBlanks,
            'eventsByDay' => $eventsByDay,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'today' => date('Y-m-d'),
        ]);
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
