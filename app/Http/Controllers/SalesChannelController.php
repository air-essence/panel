<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Models\BaselinkerSource;
use App\Models\Company;
use App\Models\SalesChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesChannelController extends Controller
{
    public function index(): View
    {
        return view('channels.index', ['channels' => SalesChannel::with('company')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return $this->form(new SalesChannel(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        SalesChannel::create($this->validated($request));

        return redirect()->route('channels.index')->with('status', 'Dodano kanał sprzedaży.');
    }

    public function edit(SalesChannel $channel): View
    {
        return $this->form($channel);
    }

    public function update(Request $request, SalesChannel $channel): RedirectResponse
    {
        $channel->update($this->validated($request, $channel));

        return redirect()->route('channels.index')->with('status', 'Zapisano zmiany.');
    }

    public function destroy(SalesChannel $channel): RedirectResponse
    {
        $channel->delete();

        return redirect()->route('channels.index')->with('status', 'Usunięto kanał sprzedaży.');
    }

    private function form(SalesChannel $channel): View
    {
        return view('channels.form', [
            'channel' => $channel,
            'companies' => Company::orderBy('name')->get(),
            // Źródła wolne albo przypisane do edytowanego kanału.
            'sources' => BaselinkerSource::where(fn ($q) => $q
                ->doesntHave('salesChannel')
                ->orWhere('key', $channel->baselinker_source ?? ''))
                ->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request, ?SalesChannel $channel = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('sales_channels')->ignore($channel)],
            'type' => ['required', Rule::enum(ChannelType::class)],
            'company_id' => ['nullable', 'exists:companies,id'],
            'baselinker_source' => ['nullable', 'string', 'max:100', Rule::unique('sales_channels')->ignore($channel)],
        ]);
        $data['company_id'] ??= null;
        $data['baselinker_source'] ??= null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
