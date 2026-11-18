<?php

namespace App\Vendor;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/** "Ekspor CSV" header action (Excel-friendly: UTF-8 BOM, semicolon) for any console list. */
class CsvExport
{
    /** @param  array<string, Closure|string>  $columns  heading => attribute or fn ($record) */
    public static function action(string $filename, Closure $query, array $columns): Action
    {
        return Action::make('csv')->label('Ekspor CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
            ->action(fn () => response()->streamDownload(function () use ($query, $columns) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_keys($columns), ';');
                foreach ($query()->lazy(200) as $record) { // lazy(), not cursor(): cursor ignores eager loads
                    fputcsv($out, array_map(fn ($c) => is_string($c) ? data_get($record, $c) : $c($record), array_values($columns)), ';');
                }
                fclose($out);
            }, $filename.'-'.now('Asia/Jakarta')->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']));
    }
}
