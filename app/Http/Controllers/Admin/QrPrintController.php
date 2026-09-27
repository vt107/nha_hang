<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids'))));

        $tables = DiningTable::query()
            ->active()
            ->when($ids, fn ($query) => $query->whereKey($ids))
            ->join('areas', 'areas.id', '=', 'dining_tables.area_id')
            ->orderBy('areas.sort_order')
            ->orderBy('dining_tables.sort_order')
            ->orderBy('dining_tables.code')
            ->select('dining_tables.*')
            ->get();

        return view('admin.qr-print', ['tables' => $tables]);
    }
}
