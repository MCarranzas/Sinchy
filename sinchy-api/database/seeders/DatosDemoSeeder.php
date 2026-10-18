<?php

namespace Database\Seeders;

use App\Models\AreaMinera;
use App\Models\Comprador;
use App\Models\CotizacionQuincenal;
use App\Models\Departamento;
use App\Models\Localidad;
use App\Models\Municipio;
use App\Models\Persona;
use App\Models\Provincia;
use App\Models\TipoCambio;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de DEMOSTRACION completos para un despliegue de vitrina
 * (demo/piloto/capacitacion): usuarios con contrasenas conocidas, tipos de
 * cambio, cotizaciones quincenales, compradores, vendedores y areas mineras.
 *
 * NO es para produccion real y NO forma parte de DatabaseSeeder: hay que
 * invocarlo a proposito. En el contenedor se dispara con la variable
 * DEMO_SEED=1 (ver docker/start.sh) una sola vez, y luego se quita.
 *
 * Es idempotente: busca por clave natural (username, fecha, ejercicio+quincena,
 * razon_social, CI, nombre del area) y actualiza en vez de duplicar, asi que se
 * puede reejecutar sin dano y sin chocar con las filas reales (por ejemplo el
 * admin que crea AdminSeeder: aqui solo se le fija la contrasena de demo).
 *
 * Requiere haber corrido antes los seeders base (Anexo F), porque las areas
 * mineras resuelven su geografia por nombre y los roles deben existir.
 */
class DatosDemoSeeder extends Seeder
{
    /** Ejercicio al que pertenecen tipos de cambio y cotizaciones de demo. */
    private const EJERCICIO = 2026;

    /** Fuente informativa que se guarda en las filas generadas. */
    private const FUENTE = 'Datos de demostracion';

    /**
     * [username, email, nombre_completo, rol, contrasena]
     *
     * @var list<array{string, string, string, string, string}>
     */
    private const USUARIOS = [
        ['admin', 'admin@sinchiwayra.local', 'Administrador del Sistema', 'admin', 'Admin123'],
        ['tecnico', 'tecnico@sinchiwayra.local', 'Tecnico de Captura', 'tecnico', 'Tecnico123'],
        ['tecnico2', 'tecnico2@sinchiwayra.local', 'Tecnico de Captura (2)', 'tecnico', 'tecnico2'],
        ['revisor', 'revisor@sinchiwayra.local', 'Revisor de Liquidaciones', 'revisor', 'Revisor123'],
        ['gerente', 'gerente@sinchiwayra.local', 'Gerente de Comercializacion', 'gerente', 'Gerente123'],
    ];

    /**
     * [nombres, apellidos, ci, nit]
     *
     * @var list<array{string, string, string, string|null}>
     */
    private const VENDEDORES = [
        ['Juan Carlos', 'Mamani Quispe', '8321456', '4983215'],
        ['Maria Fernanda', 'Choque Apaza', '9104782', null],
        ['Pedro Antonio', 'Condori Flores', '7456321', '6712458'],
        ['Ana Lucia', 'Vargas Rojas', '10234567', '8123456'],
        ['Roberto Carlos', 'Cruz Villarroel', '6987451', null],
        ['Silvia Roxana', 'Fernandez Gutierrez', '8452197', '7325498'],
        ['Luis Alberto', 'Huanca Copa', '5874123', null],
        ['Carmen Rosa', 'Ticona Mamani', '9632587', '5541236'],
        ['Jorge Andres', 'Salazar Paredes', '7123698', null],
        ['Daniela Alejandra', 'Rios Mercado', '10987654', '9987654'],
    ];

    /**
     * [razon_social, nim, nit]
     *
     * @var list<array{string, string, string}>
     */
    private const COMPRADORES = [
        ['COOPERATIVA MINERA AURIFERA DEL SUR', '02-2451-10', '1012345678'],
        ['EMPRESA MINERA ANDINA S.R.L.', '02-3107-22', '1019876543'],
        ['COMERCIALIZADORA DE MINERALES ORURO S.A.', '02-1188-15', '1005678901'],
        ['REFINERIA DE ORO BOLIVIANO S.A.', '02-4402-08', '1023456789'],
    ];

    /**
     * [quincena, fecha_inicio, fecha_fin, valor_usd_oz_t]
     *
     * @var list<array{int, string, string, string}>
     */
    private const COTIZACIONES = [
        [19, '2026-10-01', '2026-10-15', '2648.5700'],
        [20, '2026-10-16', '2026-10-31', '2748.5700'],
        [21, '2026-11-01', '2026-11-15', '2848.5700'],
        [22, '2026-11-16', '2026-11-30', '2948.5700'],
    ];

    /**
     * [nombre_area, titular, codigo_ajam, departamento, provincia, municipio, localidad]
     *
     * @var list<array{string, string, string, string, string, string, string}>
     */
    private const AREAS = [
        ['AREA MINERA CERRO RICO', 'COOPERATIVA MINERA CERRO RICO LTDA', '3456789', 'POTOSÍ', 'RAFAEL BUSTILLO', 'LLALLAGUA', 'LLALLAGUA'],
        ['AREA MINERA SIGLO XX', 'EMPRESA MINERA SIGLO XX S.A.', '4567890', 'POTOSÍ', 'RAFAEL BUSTILLO', 'LLALLAGUA', 'LLALLAGUA'],
        ['AREA MINERA SAN JOSE', 'COOPERATIVA MINERA SAN JOSE LTDA', '5678901', 'ORURO', 'CERCADO', 'ORURO', 'ORURO'],
        ['AREA MINERA MILLUNI', 'EMPRESA MINERA MILLUNI S.R.L.', '6789012', 'LA PAZ', 'MURILLO', 'LA PAZ', 'LA PAZ'],
        ['AREA MINERA ATOCHA', 'COMPANIA MINERA ATOCHA S.A.', '7890123', 'POTOSÍ', 'SUD CHICHAS', 'TUPIZA', 'TUPIZA'],
    ];

    public function run(): void
    {
        $this->sembrarUsuarios();
        $this->sembrarTiposDeCambio();
        $this->sembrarCotizaciones();
        $this->sembrarCompradores();
        $this->sembrarVendedores();
        $this->sembrarAreasMineras();

        $this->command->newLine();
        $this->command->info('Datos de DEMOSTRACION cargados (solo demo/piloto; no es produccion real).');
        $this->command->newLine();
    }

    private function sembrarUsuarios(): void
    {
        $filas = [];

        foreach (self::USUARIOS as [$username, $email, $nombre, $rol, $password]) {
            /** @var User $usuario */
            $usuario = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'email' => $email,
                    'nombre_completo' => $nombre,
                    'password' => $password,
                    'activo' => true,
                    'debe_cambiar_password' => false,
                ],
            );

            $usuario->bloqueado_hasta = null;
            $usuario->intentos_fallidos = 0;
            $usuario->save();

            $usuario->syncRoles($rol);

            $filas[] = [$username, $password, $rol];
        }

        $this->command->info('Usuarios de demostracion:');
        $this->command->table(['usuario', 'contrasena', 'rol'], $filas);
    }

    private function sembrarTiposDeCambio(): void
    {
        $inicio = sprintf('%d-10-01', self::EJERCICIO);
        $dias = 0;

        for ($n = 0; $n <= 30; $n++) {
            // 10.00 + 0.50 por dia, en centavos para no arrastrar el error de
            // un float (10.00 ... 25.00).
            $valor = number_format((1000 + 50 * $n) / 100, 4, '.', '');

            TipoCambio::query()->updateOrCreate(
                ['fecha' => date('Y-m-d', strtotime($inicio.' +'.$n.' day'))],
                ['valor' => $valor, 'fuente' => self::FUENTE],
            );

            $dias++;
        }

        $this->command->info('Tipos de cambio de '.self::EJERCICIO."-10: {$dias} dias (10.00 a 25.00).");
    }

    private function sembrarCotizaciones(): void
    {
        foreach (self::COTIZACIONES as [$quincena, $desde, $hasta, $valor]) {
            CotizacionQuincenal::query()->updateOrCreate(
                ['ejercicio' => self::EJERCICIO, 'quincena' => $quincena],
                [
                    'fecha_inicio' => $desde,
                    'fecha_fin' => $hasta,
                    'valor_usd_oz_t' => $valor,
                    'fuente' => self::FUENTE,
                ],
            );
        }

        $this->command->info('Cotizaciones quincenales de demostracion: '.count(self::COTIZACIONES).' (oct-nov '.self::EJERCICIO.').');
    }

    private function sembrarCompradores(): void
    {
        foreach (self::COMPRADORES as [$razonSocial, $nim, $nit]) {
            Comprador::query()->updateOrCreate(
                ['razon_social' => $razonSocial],
                ['nim' => $nim, 'nit' => $nit, 'activo' => true],
            );
        }

        $this->command->info('Compradores de demostracion: '.count(self::COMPRADORES).'.');
    }

    private function sembrarVendedores(): void
    {
        foreach (self::VENDEDORES as [$nombres, $apellidos, $ci, $nit]) {
            Persona::query()->firstOrCreate(
                ['ci' => $ci],
                [
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'nombre_completo' => trim($nombres.' '.$apellidos),
                    'nit' => $nit,
                    'tipo_documento' => 'CI',
                    'activo' => true,
                ],
            );
        }

        $this->command->info('Vendedores de demostracion: '.count(self::VENDEDORES).'.');
    }

    private function sembrarAreasMineras(): void
    {
        $filas = [];

        foreach (self::AREAS as [$nombre, $titular, $codigo, $dep, $prov, $mun, $loc]) {
            $departamento = Departamento::query()->where('nombre', $dep)->firstOrFail();
            $provincia = Provincia::query()
                ->where('departamento_id', $departamento->id)
                ->where('nombre', $prov)
                ->firstOrFail();
            $municipio = Municipio::query()
                ->where('provincia_id', $provincia->id)
                ->where('nombre', $mun)
                ->firstOrFail();
            $localidad = Localidad::query()
                ->where('municipio_id', $municipio->id)
                ->where('nombre', $loc)
                ->firstOrFail();

            /** @var AreaMinera $area */
            $area = AreaMinera::query()->updateOrCreate(
                ['nombre_area_minera' => $nombre],
                [
                    'titular_derecho_minero' => $titular,
                    'codigo_ajam_patente' => $codigo,
                    'departamento_id' => $departamento->id,
                    'provincia_id' => $provincia->id,
                    'municipio_id' => $municipio->id,
                    'localidad_id' => $localidad->id,
                    'activo' => true,
                ],
            );

            $filas[] = [$area->nombre_area_minera, $area->titular_derecho_minero, $dep.'/'.$prov.'/'.$mun];
        }

        $this->command->info('Areas mineras de demostracion:');
        $this->command->table(['area', 'titular', 'procedencia'], $filas);
    }
}
