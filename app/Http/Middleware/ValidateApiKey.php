<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return response()->json([
                'error' => trans('messages.auth.unauthorized'),
            ], 401);
        }

        if (! ApiKey::isValid($token)) {
            return response()->json([
                'error' => trans('messages.auth.invalid_key'),
            ], 401);
        }

        $request->attributes->set('api_key_validated', true);

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}
