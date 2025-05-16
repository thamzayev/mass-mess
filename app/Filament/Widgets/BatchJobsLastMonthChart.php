<?php

namespace App\Filament\Widgets;

use App\Models\EmailBatch;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BatchJobsLastMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Batch Jobs Created (Last 30 Days)';
    protected static ?string $pollingInterval = '30s'; // Optional: auto-refresh
    protected static ?int $sort = 2; // Controls the order of widgets on the dashboard

    protected function getData(): array
    {
        $userId = Auth::id();
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(10)->startOfDay(); // Last 30 days including today

        $data = EmailBatch::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            ])
            ->pluck('count', 'date');

        $labels = [];
        $values = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $formattedDate = $currentDate->format('Y-m-d');
            $labels[] = $currentDate->format('M d'); // e.g., Jan 01
            $values[] = $data->get($formattedDate, 0); // Use get() with a default for Carbon collection
            $currentDate->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Batch Jobs Created',
                    'data' => $values,
                    'borderColor' => 'rgb(54, 162, 235)', // Blue
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'fill' => true,
                    'tension' => 0.5,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
