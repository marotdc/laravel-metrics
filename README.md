# Metrics for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eliseekn/laravel-metrics.svg?style=flat-square)](https://packagist.org/packages/eliseekn/laravel-metrics)
[![Total Downloads](https://img.shields.io/packagist/dt/eliseekn/laravel-metrics.svg?style=flat-square)](https://packagist.org/packages/eliseekn/laravel-metrics)

Generate easily metrics and trends data of your models for your dashboards.

## Installation
```bash
composer require marotdc/laravel-metrics
```

## Features
- MySQL support
- Verbose query builder
- Custom columns and table definition
- Days and months translation with Carbon

## Usage

### With Eloquent Query

Import the `Eliseekn\LaravelMetrics\LaravelMetrics` class in your controller and use it as follows :
- Basic usage

```php
// generate trends of products amount's sum for the current year
LaravelMetrics::query(Product::query())
    ->count()
    ->byMonth()
    ->trends();

// generate trends of orders amount's sum for the last 6 months of the current year including current month
LaravelMetrics::query(Order::query())
    ->sum('amount')
    ->byMonth(6)
    ->trends();

// generate total orders amount's sum
LaravelMetrics::query(Order::query())
    ->sum('amount')
    ->byYear()
    ->metrics(); 

// generate total product count for the current day
LaravelMetrics::query(Product::query())
    ->count()
    ->byDay(1)
    ->metrics();
```

- Using custom query
```php
LaravelMetrics::query(
    Post::query()->where('user_id', auth()->id())
)
    ->count()
    ->byDay()
    ->trends();
```

- Using custom date column
```php
LaravelMetrics::query(Post::query())
    ->count()
    ->byDay()
    ->dateColumn('published_at')
    ->trends();
```

- Using date range
```php
LaravelMetrics::query(Post::query()))
    ->count()
    ->between('2020-05-01', '2022-08-21')
    ->trends();
```

- Using custom label column
```php
LaravelMetrics::query(Order::query())
    ->count()
    ->byMonth(12)
    ->labelColumn('status')
    ->trends();
```

- Using custom table
```php
LaravelMetrics::query(
    Order::query()->join('users', 'orders.id', 'users.order_id')
)
    ->count()
    ->table('users')
    ->labelColumn('name')
    ->trends();
```

### With Query Builder
```php
LaravelMetrics::query(
    DB::table('orders')
)
    ->sum('amount')
    ->byMonth()
    ->trends();
```

### With traits 

Add `HasMetrics` trait to your models and use it as follows :

```php
Order::metrics()
    ->sum('amount')
    ->byMonth()
    ->trends();
```

### Types of periods
```php
LaravelMetrics::query(...)
    ->byDay(int $count = 0) // or
    ->byWeek(int $count = 0) // or
    ->byMonth(int $count = 0) // or
    ->byYear(int $count = 0) // or
    ->between(string $startDate, string $endDate, string $dateIsoFormat)
```

**Note :** Periods are defined for the current day, week, month or year by default. However, you can define a specific value using dedicated methods. For example:

```php
// generate trends of orders count for the year 2023
LaravelMetrics::query(Order::query())
    ->count()
    ->byMonth(12)
    ->forYear(2023)
    ->labelColumn('status')
    ->trends();

// generate total orders amount's sum for the third month only
LaravelMetrics::query(Product::query())
    ->sum('amount')
    ->byMonth(1)
    ->forMonth(3)
    ->metrics();
```

```php
LaravelMetrics::query(...)
    ->forDay(int $day)
    ->forWeek(int $week)
    ->forMonth(int $month)
    ->forYear(int $year)
```

**Note :** Make sure to employ the 'fillEmptyDates' method when utilizing the 'between' period to automatically populate any missing dates with a default value. For example:

```php
LaravelMetrics::query(...)
    ->count()
    ->between(Carbon::now()->subDays(10)->format('Y-m-d'), Carbon::now()->format('Y-m-d'))
    ->fillEmptyDates()
    ->trends();
```

### Types of aggregates
```php
LaravelMetrics::query(...)
    ->count(string $column = 'id') // or
    ->average(string $column) // or
    ->sum(string $column) // or
    ->max(string $column) // or
    ->min(string $column)
```

### Types of data
```php
LaravelMetrics::query(...)
    ->trends() // or
    ->metrics()
```

## Translations

Days and months names are automatically translated using `config(app.locale)` except 'week' period.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email `eliseekn@gmail.com` instead of using the issue tracker.

## Credits

-   [N'Guessan Kouadio Elisée](https://github.com/eliseekn)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Demo

You can find a demo project [here](https://github.com/eliseekn/laravel-metrics-demo).

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
