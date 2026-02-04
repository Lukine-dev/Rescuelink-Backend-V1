<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Trusted origins (explicit whitelist)
     */
    private array $allowedOrigins = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',

    ];

    /**
     * Allow Vercel preview deployments safely
     */
    private function isAllowedVercelOrigin(?string $origin): bool
    {
        return $origin !== null &&
            preg_match('/^https:\/\/sdo-medical-(front|front-end)(-[a-z0-9]+)?\.vercel\.app$/i', $origin);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        $isAllowed =
            in_array($origin, $this->allowedOrigins, true) ||
            $this->isAllowedVercelOrigin($origin);

        /**
         * Handle preflight OPTIONS immediately
         */
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin'      => $isAllowed ? $origin : '',
                'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN, Accept',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age'            => '86400',
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($isAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN, Accept');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
