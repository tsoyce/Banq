<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Database\DatabaseSwitcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/** A34 · Админка → База данных: что подключено, проверка, переключение SQLite ⇄ MySQL с переносом, откат. Только admin. */
class DatabaseController extends Controller
{
    public function __construct(private DatabaseSwitcher $switcher) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.database.index', [
            'current' => $this->switcher->current(),
            'backups' => $this->switcher->backups(),
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json($this->switcher->test($this->input($request)));
    }

    public function switch(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $db = $this->input($request);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Подтверди переключение галочкой.']);

        try {
            $res = $this->switcher->switch($db, $request->boolean('copy', true), $request->boolean('overwrite'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password'))->withErrors(['database' => $e->getMessage()]);
        }

        $what = $res['tables'] ? "перенесено {$res['tables']} таблиц, ".number_format($res['rows'], 0, ',', ' ').' строк' : 'данные не переносились';

        return redirect()->route('admin.database.index')
            ->with('status', 'Сайт переключён на '.($db['driver'] === 'mysql' ? 'MySQL' : 'SQLite')." — {$what}. Прежняя база не тронута, .env сохранён в копию {$res['backup']}.");
    }

    public function restore(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['file' => ['required', 'string', 'max:64']]);

        try {
            $vals = $this->switcher->restore($data['file']);
        } catch (Throwable $e) {
            return back()->withErrors(['database' => $e->getMessage()]);
        }

        return redirect()->route('admin.database.index')
            ->with('status', 'Настройки базы возвращены из копии: '.($vals['DB_CONNECTION'] === 'mysql' ? 'MySQL '.($vals['DB_DATABASE'] ?? '') : 'SQLite').'.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Базу переключает только администратор.');
    }

    private function input(Request $request): array
    {
        $data = $request->validate([
            'driver' => ['required', 'in:mysql,sqlite'],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.\/-]*$/'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9_]*$/'],
        ]);
        $data['port'] = (int) ($data['port'] ?? 3306);

        return $data;
    }
}
