<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentLookupService
{
    /**
     * Consulta un DNI (8 dígitos) o RUC (11 dígitos) en RENIEC / SUNAT con múltiples proveedores y fallback.
     *
     * @return array{success: bool, message?: string, data?: array<string, string>}
     */
    public function lookup(string $type, string $number): array
    {
        $type = strtoupper(trim($type));
        $number = trim($number);

        if (! in_array($type, ['DNI', 'RUC'], true) || ! preg_match($type === 'DNI' ? '/^\d{8}$/' : '/^\d{11}$/', $number)) {
            return [
                'success' => false,
                'message' => $type === 'DNI' ? 'El DNI debe tener exactamente 8 dígitos numéricos.' : 'El RUC debe tener exactamente 11 dígitos numéricos.',
            ];
        }

        // 1. Probar en proveedores remotos oficiales / abiertos
        $remoteResult = $this->queryRemoteProviders($type, $number);
        if ($remoteResult !== null) {
            return $remoteResult;
        }

        // 2. Fallback: Buscar en la base de datos local (clientes previos o comprobantes)
        $localResult = $this->queryLocalDatabase($type, $number);
        if ($localResult !== null) {
            return $localResult;
        }

        return [
            'success' => false,
            'message' => 'No se pudo verificar el documento en RENIEC/SUNAT. Puedes ingresar los datos manualmente.',
        ];
    }

    /**
     * Consulta proveedores HTTP para DNI o RUC.
     *
     * @return array{success: bool, message?: string, data?: array<string, string>}|null
     */
    protected function queryRemoteProviders(string $type, string $number): ?array
    {
        $token = config('services.apiperu.token');
        $configuredBaseUrl = rtrim((string) config('services.apiperu.document_url'), '/');

        // Construir lista de endpoints priorizados
        $attempts = [];

        // Si hay baseUrl y token configurados en services.php (ej. v2)
        if ($configuredBaseUrl && $token) {
            $path = $type === 'DNI' ? '/reniec/dni' : '/sunat/ruc';
            $attempts[] = [
                'url' => $configuredBaseUrl.$path.'?numero='.$number,
                'token' => $token,
                'version' => 'v2',
            ];
        }

        // Endpoint v1 abierto de apis.net.pe (altamente estable para DNI y RUC)
        $attempts[] = [
            'url' => 'https://api.apis.net.pe/v1/'.($type === 'DNI' ? 'dni' : 'ruc').'?numero='.$number,
            'token' => null,
            'version' => 'v1',
        ];

        // Endpoint alternativo dniruc.apisperu.net si se configuró token
        if ($token) {
            $attempts[] = [
                'url' => 'https://apiperu.dev/api/'.($type === 'DNI' ? 'dni/'.$number : 'ruc/'.$number),
                'token' => $token,
                'version' => 'apiperu',
            ];
        }

        foreach ($attempts as $attempt) {
            try {
                $client = Http::acceptJson()->connectTimeout(4)->timeout(8);
                if (! empty($attempt['token'])) {
                    $client = $client->withToken($attempt['token']);
                }

                $response = $client->get($attempt['url']);
                if (! $response->successful()) {
                    continue;
                }

                $payload = $response->json();
                if (! is_array($payload)) {
                    continue;
                }

                // Normalizar respuesta
                $data = $payload['data'] ?? $payload;
                if (! is_array($data) || empty($data)) {
                    continue;
                }

                if ($type === 'DNI') {
                    $names = trim((string) ($data['nombres'] ?? $data['nombre'] ?? ''));
                    $paterno = trim((string) ($data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? ''));
                    $materno = trim((string) ($data['apellidoMaterno'] ?? $data['apellido_materno'] ?? ''));
                    $fullName = trim((string) ($data['nombre_completo'] ?? $data['nombre'] ?? "{$names} {$paterno} {$materno}"));

                    if ($names !== '' || $fullName !== '') {
                        return [
                            'success' => true,
                            'data' => [
                                'nombres' => $names ?: $fullName,
                                'apellido_paterno' => $paterno,
                                'apellido_materno' => $materno,
                                'nombre_completo' => $fullName ?: trim("{$names} {$paterno} {$materno}"),
                                'document_number' => $number,
                                'document_type' => 'DNI',
                            ],
                        ];
                    }
                } else {
                    $businessName = trim((string) ($data['razonSocial'] ?? $data['nombre'] ?? $data['nombre_o_razon_social'] ?? ''));
                    $address = trim((string) ($data['direccion'] ?? $data['direccion_completa'] ?? ''));

                    if ($businessName !== '') {
                        return [
                            'success' => true,
                            'data' => [
                                'nombre_o_razon_social' => $businessName,
                                'direccion_completa' => $address,
                                'estado' => (string) ($data['estado'] ?? 'ACTIVO'),
                                'condicion' => (string) ($data['condicion'] ?? 'HABIDO'),
                                'document_number' => $number,
                                'document_type' => 'RUC',
                            ],
                        ];
                    }
                }
            } catch (\Throwable $exception) {
                Log::debug('Fallo consulta documento en endpoint', [
                    'url' => $attempt['url'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Consulta en la base de datos local como respaldo si ya existe el cliente o comprobante.
     *
     * @return array{success: bool, data: array<string, string>}|null
     */
    protected function queryLocalDatabase(string $type, string $number): ?array
    {
        try {
            $customer = DB::table('customers')
                ->where('document_type', $type)
                ->where('document_number', $number)
                ->whereNotNull('full_name')
                ->where('full_name', '<>', '')
                ->first();

            if ($customer && ! empty($customer->full_name)) {
                $fullName = trim((string) $customer->full_name);

                return [
                    'success' => true,
                    'data' => [
                        'nombres' => $fullName,
                        'apellido_paterno' => '',
                        'apellido_materno' => '',
                        'nombre_completo' => $fullName,
                        'nombre_o_razon_social' => $fullName,
                        'document_number' => $number,
                        'document_type' => $type,
                    ],
                ];
            }

            $invoice = DB::table('invoices')
                ->where('customer_document_type', $type)
                ->where('customer_document_number', $number)
                ->whereNotNull('customer_name')
                ->where('customer_name', '<>', '')
                ->first();

            if ($invoice && ! empty($invoice->customer_name)) {
                $fullName = trim((string) $invoice->customer_name);

                return [
                    'success' => true,
                    'data' => [
                        'nombres' => $fullName,
                        'apellido_paterno' => '',
                        'apellido_materno' => '',
                        'nombre_completo' => $fullName,
                        'nombre_o_razon_social' => $fullName,
                        'direccion_completa' => (string) ($invoice->customer_address ?? ''),
                        'document_number' => $number,
                        'document_type' => $type,
                    ],
                ];
            }
        } catch (\Throwable) {
            // Silenciar errores de BD local
        }

        return null;
    }
}
