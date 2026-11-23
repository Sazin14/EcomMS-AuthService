<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            auth('staff')->user()?->can('customer.view'),
            403,
            'You do not have permission [customer.view].'
        );

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $search = trim((string) $request->query('search', ''));

        $query = Customer::query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $customers = $query->latest()->paginate($perPage);

        return response()->json($customers);
    }

    public function show(Customer $customer): JsonResponse
    {
        abort_unless(
            auth('staff')->user()?->can('customer.view'),
            403,
            'You do not have permission [customer.view].'
        );

        return response()->json([
            'customer' => $customer->only([
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]),
        ]);
    }
}
