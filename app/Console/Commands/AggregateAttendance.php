<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AggregateAttendance extends Command
{
    protected $signature = 'attendance:aggregate {--date=}';

    protected $description = 'Aggregate attendance daily, weekly and monthly summaries (Laravel artisan command) with pairing IN/OUT logic.';

    public function handle()
    {
        $dateOption = $this->option('date');
        $today = Carbon::now()->startOfDay();
        $targetDay = $dateOption ? Carbon::parse($dateOption)->startOfDay() : $today->subDay();
        $dayStr = $targetDay->toDateString();

        $this->info("Running daily aggregate for: {$dayStr}");
        $this->computeDailyFor($dayStr);

        // If the target day is Sunday (in Carbon: 0 = Sunday), run weekly aggregate for week starting Monday
        if ($targetDay->dayOfWeek === Carbon::SUNDAY) {
            $weekStart = $targetDay->copy()->subDays(6)->toDateString();
            $this->info("Running weekly aggregate for week starting: {$weekStart}");
            $this->aggregateWeeklyFor($weekStart);
        }

        // If target day is last day of month, run monthly aggregate
        $tomorrow = $targetDay->copy()->addDay();
        if ($tomorrow->day === 1) {
            $monthStart = $targetDay->copy()->startOfMonth()->toDateString();
            $this->info("Running monthly aggregate for month starting: {$monthStart}");
            $this->aggregateMonthlyFor($monthStart);
        }

        $this->info('Aggregation finished.');
        return 0;
    }

    protected function computeDailyFor(string $date)
    {
        $start = $date . ' 00:00:00';
        $end = Carbon::parse($date)->addDay()->toDateString() . ' 00:00:00';

        // Fetch events for the day ordered by user and time
        $sql = "SELECT user_id, occurred_at, event_type FROM attendance_events WHERE occurred_at >= ? AND occurred_at < ? ORDER BY user_id, occurred_at";
        $rows = DB::select($sql, [$start, $end]);

        // Group events by user
        $groups = [];
        foreach ($rows as $r) {
            $uid = $r->user_id;
            if (!isset($groups[$uid])) $groups[$uid] = [];
            $groups[$uid][] = ['occurred_at' => $r->occurred_at, 'event_type' => $r->event_type];
        }

        foreach ($groups as $userId => $events) {
            $totalSeconds = 0;
            $openIns = [];
            $firstIn = null;
            $lastOut = null;

            foreach ($events as $e) {
                $etype = strtoupper($e['event_type']);
                $ts = Carbon::parse($e['occurred_at']);

                if ($etype === 'IN') {
                    $openIns[] = $ts;
                    if ($firstIn === null) {
                        $firstIn = $ts;
                    }
                } elseif ($etype === 'OUT') {
                    if (!empty($openIns)) {
                        // Pair INs -> OUT
                        $openIn = array_shift($openIns);
                        $diff = $ts->diffInSeconds($openIn);
                        if ($diff > 0) $totalSeconds += $diff;
                        $lastOut = $ts;
                    } else {
                        // stray OUT without IN: ignore or treat based on policy (we ignore)
                        $lastOut = $ts;
                    }
                }
                // ignore other event_type values for duration
            }

            // If there's an unmatched IN left open at end of day, we can decide policy: ignore or cap at end of day.
            // Here we choose to ignore unmatched IN for total_work_seconds to avoid overcounting. You can change to cap at 23:59:59.

            $presence = ($totalSeconds > 0) ? 1 : 0;

            DB::table('daily_summary')->updateOrInsert(
                ['user_id' => $userId, 'day' => $date],
                [
                    'first_in' => $firstIn ? $firstIn->toDateTimeString() : null,
                    'last_out' => $lastOut ? $lastOut->toDateTimeString() : null,
                    'total_work_seconds' => $totalSeconds,
                    'presence' => $presence,
                    'updated_at' => Carbon::now(),
                ]
            );
        }

        $this->info('Daily aggregation done for '.$date.' ('.count($groups)." users)");
    }

    protected function aggregateWeeklyFor(string $weekStart)
    {
        $ws = Carbon::parse($weekStart);
        $we = $ws->copy()->addDays(6);

        $sql = "SELECT user_id,
          SUM(CASE WHEN presence = 1 THEN 1 ELSE 0 END) AS days_present,
          COALESCE(SUM(total_work_seconds),0) AS total_work_seconds
        FROM daily_summary
        WHERE day >= ? AND day <= ?
        GROUP BY user_id";

        $rows = DB::select($sql, [$ws->toDateString(), $we->toDateString()]);

        foreach ($rows as $r) {
            DB::table('weekly_summary')->updateOrInsert(
                ['user_id' => $r->user_id, 'week_start' => $ws->toDateString()],
                [
                    'week_end' => $we->toDateString(),
                    'days_present' => $r->days_present,
                    'total_work_seconds' => $r->total_work_seconds,
                    'updated_at' => Carbon::now(),
                ]
            );
        }

        $this->info('Weekly aggregation done for '.$ws->toDateString().' ('.count($rows)." users)");
    }

    protected function aggregateMonthlyFor(string $monthStart)
    {
        $ms = Carbon::parse($monthStart);
        $next = $ms->copy()->addMonth();
        $end = $next->copy()->subDay();

        $sql = "SELECT user_id,
          SUM(CASE WHEN presence = 1 THEN 1 ELSE 0 END) AS days_present,
          COALESCE(SUM(total_work_seconds),0) AS total_work_seconds
        FROM daily_summary
        WHERE day >= ? AND day <= ?
        GROUP BY user_id";

        $rows = DB::select($sql, [$ms->toDateString(), $end->toDateString()]);

        foreach ($rows as $r) {
            DB::table('monthly_summary')->updateOrInsert(
                ['user_id' => $r->user_id, 'month' => $ms->toDateString()],
                [
                    'days_present' => $r->days_present,
                    'total_work_seconds' => $r->total_work_seconds,
                    'updated_at' => Carbon::now(),
                ]
            );
        }

        $this->info('Monthly aggregation done for '.$ms->toDateString().' ('.count($rows)." users)");
    }
}