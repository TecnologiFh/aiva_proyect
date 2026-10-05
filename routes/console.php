<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('AIVA: Un empleado digital que ayuda al negocio a vender, atender y crecer.');
})->purpose('Muestra el lema oficial de AIVA');
