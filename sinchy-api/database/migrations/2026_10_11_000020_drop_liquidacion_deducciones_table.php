<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina las deducciones variables (decision del negocio).
 *
 * Queda UNA sola deduccion, el aporte de caja de salud del 1,8 % (D1), que se
 * guarda como snapshot en liquidaciones.aporte_caja_salud_bs. La tabla
 * liquidacion_deducciones almacenaba el desglose (aporte + descuentos
 * variables por liquidacion); el negocio confirmo que no hay descuentos
 * variables, asi que la tabla y su modelo se retiran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('liquidacion_deducciones');
    }

    public function down(): void
    {
        // No se re-crea la tabla: se habria borrado el desglose guardado.
    }
};
