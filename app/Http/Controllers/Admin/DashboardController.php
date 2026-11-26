<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\DepositProduct;
use App\Models\DepositRate;
use App\Models\Import;
use App\Support\Installation;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'Банков' => Bank::count(),
                'Вкладов' => DepositProduct::count(),
                'Действующих ставок' => DepositRate::current()->count(),
                'Импортов' => Import::count(),
            ],
            'imports' => Import::latest()->take(5)->get(),
            'install' => Installation::info(),
        ]);
    }
}
