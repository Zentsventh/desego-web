<?php

return [
    'table_count' => 12,
    /*
     * Modelos 3D opcionales de los platos: [id del producto => ruta o URL del archivo .glb].
     * Ejemplo: 1 => '/models/ceviche-clasico.glb'. Sin modelo, el cliente ve la foto interactiva.
     */
    'product_models' => [],
    /*
     * Cobro directo en mesa sin comisión (S/ 0): Yape/Plin, Efectivo, POS físico o Transferencia.
     */
    'direct_payment' => [
        'enabled' => true,
        'holder' => env('DINING_PAYMENT_HOLDER', 'DESEGO CEVICHERÍA PERUANA'),
        'yape_number' => env('DINING_YAPE_NUMBER', '999 888 777'),
        'plin_number' => env('DINING_PLIN_NUMBER', '999 888 777'),
        'bank_account' => env('DINING_BANK_ACCOUNT', 'BCP Soles: 191-2485930-0-42 · CCI: 00219100248593004251'),
        'methods' => ['Yape/Plin', 'Efectivo', 'Tarjeta', 'Transferencia'],
    ],
    'niubiz' => [
        'environment' => env('NIUBIZ_ENV', 'sandbox'),
        'user' => env('NIUBIZ_USER', 'integraciones@niubiz.com.pe'),
        'password' => env('NIUBIZ_PASSWORD', '_7z3@8fF'),
        'merchant_id' => env('NIUBIZ_MERCHANT_ID', '456879852'),
    ],
];
