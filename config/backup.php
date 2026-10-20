<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Copias de seguridad de la base de datos
    |--------------------------------------------------------------------------
    |
    | Las copias se guardan fuera de storage/app/public, asi que nunca quedan
    | accesibles por URL: solo se descargan desde el panel, con permiso. Es la
    | misma carpeta que usa el backup diario de Docker (docker/backup.sh), para
    | que el panel liste tambien esas copias.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('backups')),

    // Conexion a respaldar (mysql, mariadb o sqlite).
    'connection' => env('BACKUP_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    // Ruta a mysqldump. Vacio = se busca en el PATH y, en Laragon, en
    // C:\laragon\bin\mysql\*\bin (junto al PHP en uso).
    'mysqldump' => env('MYSQLDUMP_PATH'),

    // Segundos maximos que puede tardar el volcado.
    'timeout' => (int) env('BACKUP_TIMEOUT', 300),

];
