<?php
declare(strict_types=1);

namespace MaroTdc\LaravelMetrics;

use Carbon\Carbon;
use DateTime;
use MaroTdc\LaravelMetrics\Enums\Aggregate;
use MaroTdc\LaravelMetrics\Enums\Period;
use MaroTdc\LaravelMetrics\Exceptions\InvalidAggregateException;
use MaroTdc\LaravelMetrics\Exceptions\InvalidPeriodException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MaroTdc\LaravelMetrics\Exceptions\InvalidDateFormatException;
use Illuminate\Support\Facades\Config;

/**
 * LaravelMetrics
 *
 * Generate easily metrics and trends data of your models for your dashboards.
 */
class LaravelMetrics
{
    protected string $table;
    protected string $column = 'id';
    protected string|array|null $period;
    protected string $aggregate;
    protected string $dateColumn;
    protected ?string $labelColumn = null;
    protected int $count = 0;
    protected int $year;
    protected int $month;
    protected int $day;
    protected int $week;
    protected string $dateIsoFormat = 'YYYY-MM-DD';
    protected bool $fillEmptyDates = false;
    protected int $emptyDatesData = 0;

    public function __construct(protected Builder|QueryBuilder $builder)
    {
        $this->table = $this->builder->from;
        $this->dateColumn = $this->table . '.created_at';
        $this->period = null;
        $this->aggregate = Aggregate::COUNT->value;
        $this->year = Carbon::now()->year;
        $this->month = Carbon::now()->month;
        $this->day = Carbon::now()->day;
        $this->week = Carbon::now()->week;
    }

    public static function query(Builder|QueryBuilder $builder): self
    {
        return new self($builder);
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    protected function by(string $period, int $count = 0): self
    {
        $period = strtolower($period);

        if (!in_array($period, Period::values())) {
            throw new InvalidPeriodException();
        }

        $this->period = $period;
        $this->count = $count;
        return $this;
    }

    public function byDay(int $count = 0): self
    {
        return $this->by(Period::DAY->value, $count);
    }

    public function byWeek(int $count = 0): self
    {
        return $this->by(Period::WEEK->value, $count);
    }

    public function byMonth(int $count = 0): self
    {
        return $this->by(Period::MONTH->value, $count);
    }

    public function byYear(int $count = 0): self
    {
        return $this->by(Period::YEAR->value, $count);
    }

    public function between(string $start, string $end, string $dateIsoFormat = 'YYYY-MM-DD'): self
    {
        $this->checkDateFormat([$start, $end]);
        $this->period = [$start, $end];
        $this->dateIsoFormat = $dateIsoFormat;
        return $this;
    }

    public function forDay(int $day): self
    {
        $this->day = $day;
        return $this;
    }

    public function forWeek(int $week): self
    {
        $this->week = $week;
        return $this;
    }

    public function forMonth(int $month): self
    {
        $this->month = $month;
        return $this;
    }

    public function forYear(int $year): self
    {
        $this->year = $year;
        return $this;
    }

    protected function aggregate(string $aggregate, string $column): self
    {
        $aggregate = strtolower($aggregate);

        if (!in_array($aggregate, Aggregate::values())) {
            throw new InvalidAggregateException();
        }

        $this->aggregate = $aggregate;
        $this->column = $this->table . '.' . $column;
        return $this;
    }

    public function count(string $column = 'id'): self
    {
        return $this->aggregate(Aggregate::COUNT->value, $column);
    }

    public function average(string $column): self
    {
        return $this->aggregate(Aggregate::AVERAGE->value, $column);
    }

    public function sum(string $column): self
    {
        return $this->aggregate(Aggregate::SUM->value, $column);
    }

    public function max(string $column): self
    {
        return $this->aggregate(Aggregate::MAX->value, $column);
    }

    public function min(string $column): self
    {
        return $this->aggregate(Aggregate::MIN->value, $column);
    }

    public function dateColumn(string $column): self
    {
        $this->dateColumn = $this->table . '.' . $column;
        return $this;
    }

    public function labelColumn(string $column): self
    {
        $this->labelColumn = $this->table . '.' . $column;
        return $this;
    }

    public function fillEmptyDates(int $value = 0): self
    {
        $this->fillEmptyDates = true;
        $this->emptyDatesData = $value;
        return $this;
    }

    protected function metricsData(): mixed
    {
        if (is_array($this->period)) {
            return $this->builder
                ->selectRaw($this->asData())
                ->whereBetween(DB::raw("date($this->dateColumn)"), [$this->period[0], $this->period[1]])
                ->first();
        }

        return match ($this->period) {
            Period::DAY->value => $this->builder
                ->selectRaw($this->asData())
                ->whereYear($this->dateColumn, $this->year)
                ->whereMonth($this->dateColumn, $this->month)
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw("day($this->dateColumn)"), $this->day);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw("day($this->dateColumn)"), $this->getDayPeriod());
                })
                ->first(),

            Period::WEEK->value => $this->builder
                ->selectRaw($this->asData())
                ->whereYear($this->dateColumn, $this->year)
                ->whereMonth($this->dateColumn, $this->month)
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw($this->formatPeriod(Period::WEEK->value)), $this->week);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw($this->formatPeriod(Period::WEEK->value)), $this->getWeekPeriod());
                })
                ->first(),

            Period::MONTH->value => $this->builder
                ->selectRaw($this->asData())
                ->whereYear($this->dateColumn, $this->year)
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw($this->formatPeriod(Period::MONTH->value)), $this->month);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw($this->formatPeriod(Period::MONTH->value)), $this->getMonthPeriod());
                })
                ->first(),

            Period::YEAR->value => $this->builder
                ->selectRaw($this->asData())
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw($this->formatPeriod(Period::YEAR->value)), $this->year);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw($this->formatPeriod(Period::YEAR->value)), [
                        Carbon::now()->subYears($this->count)->year, $this->year
                    ]);
                })
                ->first(),

            default => $this->builder
                ->selectRaw($this->asData())
                ->first(),
        };
    }

    protected function trendsData(): Collection
    {
        if (is_array($this->period)) {
            return $this->builder
                ->selectRaw($this->asData() . ", " . $this->asLabel("date($this->dateColumn)", false))
                ->whereBetween(DB::raw("date($this->dateColumn)"), [$this->period[0], $this->period[1]])
                ->groupBy('label')
                ->orderBy('label')
                ->get();
        }

        return match ($this->period) {
            Period::DAY->value => $this->builder
                ->selectRaw($this->asData() . ", " . $this->asLabel(Period::DAY->value))
                ->whereYear($this->dateColumn, $this->year)
                ->whereMonth($this->dateColumn, $this->month)
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw("day($this->dateColumn)"), $this->day);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw("day($this->dateColumn)"), $this->getDayPeriod());
                })
                ->groupBy('label')
                ->orderBy('label')
                ->get(),

            Period::WEEK->value => $this->builder
                ->selectRaw($this->asData() . ", " . $this->asLabel(Period::WEEK->value))
                ->whereYear($this->dateColumn, $this->year)
                ->whereMonth($this->dateColumn, $this->month)
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw($this->formatPeriod(Period::WEEK->value)), $this->week);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw($this->formatPeriod(Period::WEEK->value)), $this->getWeekPeriod());
                })
                ->groupBy('label')
                ->orderBy('label')
                ->get(),

            Period::MONTH->value => $this->builder
                ->selectRaw($this->asData() . ", " . $this->asLabel(Period::MONTH->value))
                ->whereYear($this->dateColumn, $this->year)
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw($this->formatPeriod(Period::MONTH->value)), $this->month);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw($this->formatPeriod(Period::MONTH->value)), $this->getMonthPeriod());
                })
                ->groupBy('label')
                ->orderBy('label')
                ->get(),

            Period::YEAR->value => $this->builder
                ->selectRaw($this->asData() . ", " . $this->asLabel(Period::YEAR->value))
                ->when($this->count === 1, function (Builder|QueryBuilder $query) {
                    return $query->where(DB::raw($this->formatPeriod(Period::YEAR->value)), $this->year);
                })
                ->when($this->count > 1, function (Builder|QueryBuilder $query) {
                    return $query->whereBetween(DB::raw($this->formatPeriod(Period::YEAR->value)), [
                        Carbon::now()->subYears($this->count)->year, $this->year
                    ]);
                })
                ->groupBy('label')
                ->orderBy('label')
                ->get(),

            default => $this->builder
                ->selectRaw($this->asData() . ", " . $this->asLabel())
                ->groupBy('label')
                ->orderBy('label')
                ->get(),
        };
    }

    protected function asData(): string
    {
        return "$this->aggregate($this->column) as data";
    }

    protected function asLabel(?string $label = null, bool $format = true): string
    {
        if (is_null($this->labelColumn)) {
            $label = !$format ? $label : $this->formatPeriod($label);
        } else {
            $label = $this->labelColumn;
        }

        return $label . " as label";
    }

    protected function generateDateRange($startDate, $endDate)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $dateRange = [];
        $currentDate = $startDate;

        while ($currentDate <= $endDate) {
            $dateRange[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        return $dateRange;
    }

    protected function fillEmptyDatesFromCollection(Collection $data): Collection
    {
        $dateRange = $this->generateDateRange($this->period[0], $this->period[1]);
        $mergedData = [];

        foreach ($dateRange as $date) {
            $dataForDate = $data->where('label', $date)->first();

            if ($dataForDate) {
                $mergedData[] = [
                    'label' => $dataForDate->label,
                    'data' => $dataForDate->data
                ];
            } else {
                $mergedData[] = [
                    'label' => $date,
                    'data' => $this->emptyDatesData
                ];
            }
        }

        return collect($mergedData);
    }

    /**
     * Generate metrics data
     */
    public function metrics(): mixed
    {
        $metricsData = $this->metricsData();
        return is_null($metricsData) ? 0 : $metricsData->data;
    }

    /**
     * Generate trends data for charts
     */
    public function trends(): array
    {
        $trendsData = $this->trendsData();

        if ($this->fillEmptyDates) {
            $trendsData = $this->fillEmptyDatesFromCollection($trendsData);
        }

        $trendsData = $this->formatDate($trendsData);

        $result = [
            'labels' => [],
            'data' => []
        ];

        foreach ($trendsData as $data) {
            $result['labels'][] = $data['label'];
            $result['data'][] = $data['data'];
        }

        return $result;
    }

    protected function carbon(): Carbon
    {
        return Carbon::parse($this->year . '-' . $this->month . '-' . $this->day);
    }

    protected function getDayPeriod(): array
    {
        $day = $this->month !== Carbon::now()->month ? $this->carbon()->endOfMonth()->day : $this->day;
        $diff = $day - $this->carbon()->startOfMonth()->day;

        if ($diff < $this->count) {
            return [$this->carbon()->startOfMonth()->day, $day];
        }

        return [$this->carbon()->subDays($this->count)->day, $day];
    }

    protected function getWeekPeriod(): array
    {
        $week = $this->month !== Carbon::now()->month ? $this->carbon()->endOfMonth()->week : $this->week;
        $diff = $week - $this->carbon()->startOfMonth()->week;

        if ($diff < $this->count) {
            return [$this->carbon()->startOfMonth()->week, $week];
        }

        return [$this->carbon()->subWeeks($this->count)->week, $week];
    }

    protected function getMonthPeriod(): array
    {
        $month = $this->year !== Carbon::now()->year ? $this->carbon()->endOfYear()->month : $this->month;
        $diff = $month - $this->carbon()->startOfYear()->month;

        if ($diff < $this->count) {
            return [$this->carbon()->startOfYear()->month, $month];
        }

        return [$this->carbon()->subMonths($this->count)->month, $month];
    }

    protected function locale(): string
    {
        return Config::get('app.locale');
    }

    protected function formatPeriod(string $period): string
    {
        return match ($period) {
            Period::DAY->value => "weekday($this->dateColumn)",
            Period::WEEK->value => "week($this->dateColumn)",
            Period::MONTH->value => "month($this->dateColumn)",
            Period::YEAR->value => "year($this->dateColumn)",
            default => '',
        };
    }

    protected function formatDate(Collection $data): Collection
    {
        return $data->map(function ($datum) {
            if (!is_numeric($datum['label']) && !DateTime::createFromFormat('Y-m-d',$datum['label'])) {
                return $datum;
            }

            if ($this->period === Period::MONTH->value) {
                $datum['label'] = Carbon::parse($this->year . '-' . $datum['label'])->locale(self::locale())->monthName;
            } elseif ($this->period === Period::DAY->value) {
                $datum['label'] = Carbon::parse($this->year . '-' . $this->month . '-' . $datum['label'])->locale(self::locale())->dayName;
            } elseif ($this->period === Period::WEEK->value) {
                $datum['label'] = 'Week ' . $datum['label'];
            } elseif ($this->period === Period::YEAR->value) {
                $datum['label'] = intval($datum['label']);
            } else {
                $datum['label'] = Carbon::parse($datum['label'])->locale(self::locale())->isoFormat($this->dateIsoFormat);
            }

            return $datum;
        });
    }

    protected function checkDateFormat(array $dates): void
    {
        foreach ($dates as $date) {
            $d = DateTime::createFromFormat('Y-m-d', $date);

            if (!$d || $d->format('Y-m-d') !== $date) {
                throw new InvalidDateFormatException();
            }
        }
    }
}
