<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Installer\DatabaseConfigurator;
use App\Services\Installer\EnvironmentChecker;
use App\Services\Installer\EnvWriter;
use App\Support\Installation;
use App\Support\Settings;
use Database\Seeders\BankSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

/**
 * A03 · Мастер установки при первом запуске.
 * Шаги: 1 окружение → 2 база данных → 3 сайт и администратор → готово.
 */
class InstallController extends Controller
{
    public function __construct(private DatabaseConfigurator $db) {}

    public function welcome(EnvironmentChecker $checker): View
    {
        return view('install.welcome', ['checks' => collect($checker->checks())->groupBy('group'), 'passes' => $checker->passes(), 'step' => 1]);
    }

    public function database(): View
    {
        return view('install.database', ['step' => 2, 'db' => session('install.db', [
            'driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'database' => '', 'username' => '', 'password' => '', 'prefix' => '',
        ])]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        return response()->json($this->db->test($this->dbInput($request)));
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        $db = $this->dbInput($request);
        $test = $this->db->test($db);
        if (! $test['ok']) {
            return back()->withInput()->withErrors(['database' => $test['message']]);
        }

        try {
            (new EnvWriter)->set($this->db->envValues($db) + ['APP_URL' => $request->getSchemeAndHttpHost()]);
            $this->db->apply($db);
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['database' => 'Не удалось создать таблицы: '.$e->getMessage()]);
        }

        session(['install.db' => array_merge($db, ['password' => '']), 'install.db_ok' => true]);

        return redirect()->route('install.site');
    }

    public function site(): View|RedirectResponse
    {
        if (! session('install.db_ok')) {
            return redirect()->route('install.database');
        }

        return view('install.site', ['step' => 3, 'defaults' => Settings::defaults()]);
    }

    public function finish(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_tagline' => ['nullable', 'string', 'max:160'],
            'site_description' => ['nullable', 'string', 'max:300'],
            'founded_year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'admin_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:160'],
            'admin_password' => ['required', 'string', 'min:10', 'confirmed'],
            'seed_banks' => ['nullable', 'boolean'],
        ]);

        $settings->set([
            'site.name' => $data['site_name'],
            'site.tagline' => $data['site_tagline'] ?? null,
            'site.description' => $data['site_description'] ?? null,
            'site.founded_year' => (int) $data['founded_year'],
            'site.copyright' => $data['site_name'],
        ]);

        $admin = User::updateOrCreate(['email' => $data['admin_email']], [
            'name' => $data['admin_name'],
            'password' => Hash::make($data['admin_password']),
            'role' => 'admin',
        ]);

        if ($request->boolean('seed_banks')) {
            (new BankSeeder)->run();
        }

        (new EnvWriter)->set(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_NAME' => $data['site_name']]);
        Installation::markInstalled(['version' => config('banq.version')]);
        $request->session()->forget(['install.db', 'install.db_ok']);
        Auth::login($admin, true);

        return redirect()->route('admin.dashboard')->with('status', 'Установка завершена. Добро пожаловать в TSOY.IN!');
    }

    private function dbInput(Request $request): array
    {
        $data = $request->validate([
            'driver' => ['required', 'in:mysql,sqlite'],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required_if:driver,mysql', 'nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9_.\/-]*$/'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9_]*$/'],
        ]);
        $data['port'] = (int) ($data['port'] ?? 3306);

        return $data;
    }
}
