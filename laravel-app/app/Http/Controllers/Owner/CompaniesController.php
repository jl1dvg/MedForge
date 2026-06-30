<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompaniesController extends Controller
{
    public function index(): View
    {
        $companies = Company::orderBy('name')->get();

        return view('owner.companies.index', [
            'pageTitle' => 'Panel de propietario — Empresas',
            'companies' => $companies,
        ]);
    }

    public function edit(int $id): View
    {
        $company = Company::findOrFail($id);

        return view('owner.companies.edit', [
            'pageTitle' => "Editar empresa: {$company->name}",
            'company'   => $company,
        ]);
    }

    public function update(int $id, Request $request): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'service_mode'     => ['required', 'in:auto,on,off'],
            'readonly_start'   => ['nullable', 'date'],
            'readonly_end'     => ['nullable', 'date', 'after_or_equal:readonly_start'],
            'readonly_message' => ['nullable', 'string', 'max:500'],
            'is_active'        => ['boolean'],
        ]);

        $company->update([
            'service_mode'     => $validated['service_mode'],
            'readonly_start'   => $validated['readonly_start'] ?? null,
            'readonly_end'     => $validated['readonly_end'] ?? null,
            'readonly_message' => $validated['readonly_message'] ?? null,
            'is_active'        => $request->boolean('is_active'),
        ]);

        return redirect('/owner/companies')->with('success', "Empresa «{$company->name}» actualizada correctamente.");
    }
}
