<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** A05 · Технические настройки сайта: название, логотип, фавикон, подвал, копирайт, годы жизни. */
class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('admin.settings.edit', ['s' => $settings->all(), 'years' => $settings->years()]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_tagline' => ['nullable', 'string', 'max:160'],
            'site_description' => ['nullable', 'string', 'max:300'],
            'founded_year' => ['required', 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'copyright' => ['nullable', 'string', 'max:120'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'counters' => ['nullable', 'string', 'max:5000'],
            'theme_default' => ['required', 'in:dark,light'],
            'default_country' => ['nullable', 'exists:countries,slug'], // A21
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        $values = [
            'site.name' => $data['site_name'],
            'site.tagline' => $data['site_tagline'] ?? null,
            'site.description' => $data['site_description'] ?? null,
            'site.founded_year' => (int) $data['founded_year'],
            'site.copyright' => $data['copyright'] ?? null,
            'site.footer_text' => $data['footer_text'] ?? null,
            'site.contact_email' => $data['contact_email'] ?? null,
            'site.counters' => $data['counters'] ?? null,
            'theme.default' => $data['theme_default'],
        ];
        if (! empty($data['default_country'])) {
            $values['country.default'] = $data['default_country'];
        }

        foreach (['logo', 'favicon'] as $field) {
            if ($request->boolean("remove_{$field}")) {
                $values["site.{$field}"] = null;
            }
            if ($request->file($field)) {
                $values["site.{$field}"] = $this->store($request->file($field), $field);
            }
        }

        $settings->set($values);

        return back()->with('status', 'Настройки сохранены');
    }

    private function store(UploadedFile $file, string $prefix): string
    {
        $name = $prefix.'-'.Str::random(8).'.'.$file->getClientOriginalExtension();
        $file->move(public_path('uploads/branding'), $name);

        return '/uploads/branding/'.$name;
    }
}
