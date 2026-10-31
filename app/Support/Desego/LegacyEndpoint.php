<?php

namespace App\Support\Desego;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LegacyEndpoint
{
    /** @return array<string, mixed> */
    public function call(string $module, array $data = [], array $query = [], string $method = 'GET'): array
    {
        $controller = config('desego_routes.'.$module);
        abort_unless(is_string($controller), 404);
        $original = request();
        $server = $original->server->all();
        $server['HTTP_X_CSRF_TOKEN'] = $original->session()->token();
        $server['CONTENT_TYPE'] = 'application/json';
        $synthetic = Request::create($original->url(), $method, [], $original->cookies->all(), [], $server, json_encode($data, JSON_THROW_ON_ERROR));
        $synthetic->query->replace($query);
        $synthetic->setLaravelSession($original->session());
        app()->instance('request', $synthetic);
        try {
            try {
                $response = app($controller)();
            } catch (HttpResponseException $exception) {
                $response = $exception->getResponse();
            }
            $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if ($response->getStatusCode() >= 400) {
                if (in_array($response->getStatusCode(), [401, 403, 404], true)) {
                    abort($response->getStatusCode(), $payload['message'] ?? 'Acceso no disponible.');
                }
                throw ValidationException::withMessages(['request' => $payload['message'] ?? 'No se pudo completar la solicitud.']);
            }

            return $payload;
        } finally {
            app()->instance('request', $original);
        }
    }
}
