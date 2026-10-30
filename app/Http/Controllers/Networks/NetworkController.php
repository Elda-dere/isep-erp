<?php

namespace App\Http\Controllers\Networks;

use App\Http\Requests\Networks\NetworkRequest;
use App\Models\ApprovedNetwork;
use App\Services\NetworkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NetworkController
{
    public function __construct(private NetworkService $networks) {}

    public function index(Request $request): Response
    {
        $ip = (string) $request->ip();

        return Inertia::render('Networks/Index', [
            'networks' => ApprovedNetwork::query()
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(25)
                ->through(fn (ApprovedNetwork $network) => [
                    'id' => $network->id,
                    'name' => $network->name,
                    'ip_range' => $network->ip_range,
                    'is_active' => $network->is_active,
                ]),
            // The address exactly as this server sees it, so the administrator can add the right range.
            'yourIp' => $ip,
            'hasActive' => ApprovedNetwork::query()->where('is_active', true)->exists(),
            'yourIpAllowed' => ApprovedNetwork::allows($ip),
        ]);
    }

    public function store(NetworkRequest $request): RedirectResponse
    {
        $this->networks->create($request->validated());

        return back()->with('success', 'Network added.');
    }

    public function update(NetworkRequest $request, ApprovedNetwork $network): RedirectResponse
    {
        $this->networks->update($network, $request->validated());

        return back()->with('success', 'Network updated.');
    }

    public function activate(ApprovedNetwork $network): RedirectResponse
    {
        $this->networks->setActive($network, true);

        return back()->with('success', 'Network activated.');
    }

    public function deactivate(ApprovedNetwork $network): RedirectResponse
    {
        $this->networks->setActive($network, false);

        return back()->with('success', 'Network deactivated.');
    }
}