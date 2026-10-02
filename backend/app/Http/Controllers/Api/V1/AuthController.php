<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            $this->audit->log('auth.failed_login', extraContext: [
                'email' => $request->validated('email'),
            ]);

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        $token = JWTAuth::fromUser($user);
        $this->audit->log('auth.login', $user, actor: $user);

        return response()->json([
            'message' => 'Authenticated.',
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => $this->userPayload($user->fresh()),
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth('api')->user();
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Throwable) {
            // Token already invalid/expired — still a successful logout.
        }

        if ($user) {
            $this->audit->log('auth.logout', $user, actor: $user);
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
        } catch (\Throwable) {
            return response()->json(['message' => 'Token cannot be refreshed.'], 401);
        }

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json(['data' => $this->userPayload($user)]);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing(['agency', 'roles.permissions']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'agency' => $user->agency ? [
                'id' => $user->agency->id,
                'name' => $user->agency->name,
                'slug' => $user->agency->slug,
            ] : null,
            'roles' => $user->roles->pluck('slug')->all(),
            'permissions' => $user->permissionSlugs(),
        ];
    }
}
