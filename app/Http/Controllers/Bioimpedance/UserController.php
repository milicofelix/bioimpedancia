<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly BioimpedanceController $workflow) {}

    public function store(Request $request): JsonResponse
    {
        return $this->workflow->storeUser($request);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        return $this->workflow->updateUser($request, $user);
    }

    public function inactivate(Request $request, User $user): JsonResponse
    {
        return $this->workflow->inactivateUser($request, $user);
    }
}
