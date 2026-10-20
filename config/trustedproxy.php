<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | En produccion la app va detras de un reverse proxy con TLS (Caddy, Nginx;
    | ver DEPLOY.md §4). Sin confiar en el, Laravel ve la IP del proxy en vez de
    | la del visitante (los limites de login y checkout pasan a ser uno solo
    | para todos) y cree que la conexion es http (no envia HSTS y las URLs
    | firmadas no validan).
    |
    | Lista separada por comas de IPs o rangos CIDR, o "*" para confiar en
    | cualquier origen (solo si el puerto de la app NO es accesible desde
    | internet). Vacio = no se confia en ningun proxy, que es lo correcto en
    | local.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') === '*'
        ? '*'
        : (array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))) ?: null),

];
