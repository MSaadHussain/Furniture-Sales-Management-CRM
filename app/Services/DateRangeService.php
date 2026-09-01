<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Resolves the dashboard/report date filters listed in requirements 16.2 and
 * supplies the matching previous period used for growth comparisons (43).
 */
class DateRangeService
{
    public const DEFAULT = 'this_month';

    /** @return array{0:CarbonImmutable,1:CarbonImmutable,2:string,3:string} from, to, label, preset */
    public function resolve(Request $request): array
    {
        $preset = (string) $request->input('range', self::DEFAULT);

        if (! array_key_exists($preset, self::presets())) {
            $preset = self::DEFAULT;
        }

        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'today'       => [$today, $today],
            'yesterday'   => [$today->subDay(), $today->subDay()],
            'last_7'      => [$today->subDays(6), $today],
            'this_week'   => [$today->startOfWeek(), $today->endOfWeek()],
            'this_month'  => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month'  => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_3'      => [$today->subMonthsNoOverflow(3)->addDay(), $today],
            'last_6'      => [$today->subMonthsNoOverflow(6)->addDay(), $today],
            'this_year'   => [$today->startOfYear(), $today->endOfYear()],
            'custom'      => $this->custom($request, $today),
            default       => [$today->startOfMonth(), $today->endOfMonth()],
        };

        return [
            $from->startOfDay(),
            $to->endOfDay(),
            $this->label($preset, $from, $to),
            $preset,
        ];
    }

    /**
     * The equally long window immediately before the selected one, so
     * "this month vs last month" and "last 7 days vs the 7 before" both work.
     */
    public function previous(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // A whole calendar month compares against the whole previous calendar
        // month, so "this month vs last month" is like-for-like rather than a
        // rolling 30-day window. Same idea for a whole year.
        if ($from->isSameMonth($to) && $from->isSameDay($from->startOfMonth()) && $to->isSameDay($to->endOfMonth())) {
            $previous = $from->subMonthNoOverflow();

            return [$previous->startOfMonth()->startOfDay(), $previous->endOfMonth()->endOfDay()];
        }

        if ($from->isSameDay($from->startOfYear()) && $to->isSameDay($to->endOfYear())) {
            $previous = $from->subYear();

            return [$previous->startOfYear()->startOfDay(), $previous->endOfYear()->endOfDay()];
        }

        // Otherwise: the equally long window immediately before this one.
        $days     = $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $prevTo   = $from->startOfDay()->subDay()->endOfDay();
        $prevFrom = $prevTo->startOfDay()->subDays($days - 1)->startOfDay();

        return [$prevFrom, $prevTo];
    }

    /** Growth percentage, or null when there is no baseline to compare against. */
    public static function growth(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** Formats a growth value, showing N/A when the baseline was zero. */
    public static function growthLabel(?float $growth): string
    {
        if ($growth === null) {
            return 'N/A';
        }

        return ($growth >= 0 ? '+' : '') . number_format($growth, 1) . '%';
    }

    /** @return array<string,string> */
    public static function presets(): array
    {
        return [
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            'last_7'     => 'Last 7 days',
            'this_week'  => 'This week',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'last_3'     => 'Last 3 months',
            'last_6'     => 'Last 6 months',
            'this_year'  => 'This year',
            'custom'     => 'Custom range',
        ];
    }

    private function custom(Request $request, CarbonImmutable $today): array
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'))
            : $today->startOfMonth();

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->input('to'))
            : $today;

        // Tolerate a reversed range rather than returning an empty report.
        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function label(string $preset, CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($preset !== 'custom') {
            return self::presets()[$preset];
        }

        return $from->isSameDay($to)
            ? $from->format('d M Y')
            : $from->format('d M Y') . ' - ' . $to->format('d M Y');
    }
}
