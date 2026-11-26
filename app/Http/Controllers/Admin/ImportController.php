<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Services\Import\DepositImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** A08 · Импорт ставок из Excel: загрузка → предпросмотр → запись. */
class ImportController extends Controller
{
    public function __construct(private DepositImporter $importer) {}

    public function index(): View
    {
        return view('admin.import.index', ['imports' => Import::latest()->paginate(20)]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,ods', 'max:10240']]);
        $file = $request->file('file');
        $stored = $file->storeAs('imports', now()->format('Ymd-His').'-'.Str::random(6).'.'.$file->getClientOriginalExtension());

        try {
            $preview = $this->importer->preview(storage_path('app/private/'.$stored));
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'Не удалось прочитать файл: '.$e->getMessage()]);
        }

        session(['import.pending' => ['path' => $stored, 'name' => $file->getClientOriginalName()]]);

        return view('admin.import.preview', ['preview' => $preview, 'filename' => $file->getClientOriginalName()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = session('import.pending');
        abort_unless($pending, 419, 'Сначала загрузи файл');

        $sheets = $request->validate(['sheets' => ['nullable', 'array'], 'sheets.*' => ['string']])['sheets'] ?? null;
        $import = $this->importer->import(storage_path('app/private/'.$pending['path']), $pending['name'], $request->user()->id, $request->boolean('create_banks'), $sheets);
        $import->update(['path' => $pending['path']]);
        session()->forget('import.pending');

        return redirect()->route('admin.import.index')->with('status', "Импорт готов: новых {$import->rows_created}, обновлено {$import->rows_updated}, без изменений/пропущено {$import->rows_skipped}.");
    }
}
