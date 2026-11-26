<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** A06 · Справочник банков в админке (список + редактирование). */
class BankController extends Controller
{
    public function index(): View
    {
        return view('admin.banks.index', ['banks' => Bank::withCount('depositProducts')->orderBy('sort')->orderBy('name')->get()]);
    }

    public function edit(Bank $bank): View
    {
        return view('admin.banks.edit', ['bank' => $bank]);
    }

    public function update(Request $request, Bank $bank): RedirectResponse
    {
        $bank->update($request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('banks')->ignore($bank)],
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['nullable', 'string', 'max:80'],
            'name_kz' => ['nullable', 'string', 'max:160'],
            'website' => ['nullable', 'url', 'max:255'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'status' => ['required', 'in:active,revoked,merged'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]));

        return redirect()->route('admin.banks.index')->with('status', "Банк «{$bank->displayName()}» сохранён");
    }
}
