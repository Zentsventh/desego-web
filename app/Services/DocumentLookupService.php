<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentLookupService
{
    /**
     * Consulta un DNI (8 dígitos) o RUC (11 dígitos) en API Perú.
     *
     * @return array{success: bool, message?: string, data?: array<string, string>}
     */
    public function lookup(string $type, string $number): array
    {
        $type = strtoupper($type);
        if (! in_array($type, ['DNI', 'RUC'], true) || ! preg_match($type === 'DNI' ? '/^\d{8}$/' : '/^\d{11}$/', $number)) {
            return ['success' => false, 'message' => 'Documento inválido.'];
        }

        $token = config('services.apiperu.token');
        $baseUrl = rtrim((string) config('services.apiperu.document_url'), '/');
        if (! $token || ! $baseUrl) {
            return ['success' => false, 'message' => 'La consulta de documentos no está configurada. Ingresa los datos manualmente.'];
        }

        $path = $type === 'DNI' ? '/reniec/dni' : '/sunat/ruc';
        $endpoints = [
            'https://api.apis.net.pe/v1/'.strtolower($type).'?numero='.$number,
            $baseUrl.$path.'?numero='.$number,
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(12)->get($endpoint);
            } catch (\Throwable $exception) {
                Log::warning('No se pudo consultar el proveedor de documentos.', ['exception' => $exception::class]);

                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $data = $response->json();
            if ($type === 'DNI') {
                $names = $data['nombres'] ?? $data['nombre'] ?? '';
                if ($names !== '') {
                    return ['success' => true, 'data' => [
                        'nombres' => $names,
                        'apellido_paterno' => $data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? '',
                        'apellido_materno' => $data['apellidoMaterno'] ?? $data['apellido_materno'] ?? '',
                    ]];
                }
            } else {
                $businessName = $data['razonSocial'] ?? $data['nombre'] ?? $data['nombre_o_razon_social'] ?? '';
                if ($businessName !== '') {
                    return ['success' => true, 'data' => [
                        'nombre_o_razon_social' => $businessName,
                        'direccion_completa' => $data['direccion'] ?? $data['direccion_completa'] ?? '',
                    ]];
                }
            }
        }

        return ['success' => false, 'message' => 'No se pudo verificar el documento. Ingresa los datos manualmente.'];
    }
}
