<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountListRequest;
use App\Models\User;
use App\Services\Billing\StorageQuotaService;
use Illuminate\View\View;

class ClientController extends Controller
{
    private const PER_PAGE = 15;

    public function index(AccountListRequest $request): View
    {
        $clients = User::clients()
            ->when($request->search(), fn ($query, $term) => $this->search($query, $term))
            ->when($request->status(), fn ($query, $status) => $query->where('status', $status->value))
            ->latest('created_at')
            ->latest('user_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.clients', [
            'clients' => $clients,
            'total' => User::clients()->count(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(string $id, StorageQuotaService $quota): View
    {
        $client = User::clients()->with([
            'accountActions.admin',
            'subscription.plan',
            'subscription.charges' => fn ($query) => $query->latest()->limit(5),
        ])->findOrFail($id);

        return view('admin.client-details', [
            'client' => $client,
            'storageUsedBytes' => $client->subscription ? $quota->usedBytes($client) : null,
        ]);
    }

    private function search($query, string $term)
    {
        // Escape LIKE wildcards so "50%" or "_" search literally.
        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $query->where(fn ($q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('mobile', 'like', $like));
    }
}
