<?php

return [

    /*
     * Eyni anda bir istifadəçinin neçə cihazdan aktiv sessiyası ola bilər.
     * Login zamanı bu limitdən artıq sessiya varsa ən köhnəsi silinir.
     */
    'max_devices' => env('MAX_DEVICES', 3),

];
