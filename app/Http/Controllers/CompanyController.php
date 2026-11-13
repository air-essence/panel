<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('companies.index', ['companies' => Company::withCount('salesChannels')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('companies.form', ['company' => new Company]);
    }

    public function store(Request $request): RedirectResponse
    {
        Company::create($this->validated($request));

        return redirect()->route('companies.index')->with('status', 'Dodano spółkę.');
    }

    public function edit(Company $company): View
    {
        return view('companies.form', ['company' => $company]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company->update($this->validated($request, $company));

        return redirect()->route('companies.index')->with('status', 'Zapisano zmiany.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('companies.index')->with('status', 'Usunięto spółkę.');
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('companies')->ignore($company)],
            'short_name' => ['required', 'string', 'max:50', Rule::unique('companies')->ignore($company)],
        ]);
    }
}
