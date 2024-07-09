<?php
class Laar extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Eliminar el campo 'imagenes' si existe
        if (isset($data['imagenes'])) {
            unset($data['imagenes']);
        }

        $estadoActualCodigo = $data['estadoActualCodigo'];
        $novedades = $data['novedades'];
        $noGuia = $data['noGuia'];

        $esEntregado = false;
        $esDevolucion = false;
        $notificar = false;

        // Regla para Entregado
        if ($estadoActualCodigo == 7) {
            foreach ($novedades as $novedad) {
                if ($novedad['codigoTipoNovedad'] == 43) {
                    $esEntregado = true;
                    break;
                }
            }
        }

        // Regla para Devolución
        if ($estadoActualCodigo != 7 && $estadoActualCodigo != 9) {
            foreach ($novedades as $novedad) {
                if ($novedad['codigoTipoNovedad'] == 42 || $novedad['codigoTipoNovedad'] == 92) {
                    $esDevolucion = true;
                    break;
                }
            }
        }

        // Regla para Devolución cuando entregado
        if ($estadoActualCodigo == 7) {
            foreach ($novedades as $novedad) {
                if ($novedad['codigoTipoNovedad'] == 42 || $novedad['codigoTipoNovedad'] == 96) {
                    $esDevolucion = true;
                    break;
                }
            }
        }

        // Verificar novedades no coincidentes para notificación
        foreach ($novedades as $novedad) {
            if (!in_array($novedad['codigoTipoNovedad'], [42, 43, 92, 96])) {
                $notificar = true;
                break;
            }
        }

        if ($esEntregado) {
            $this->model->actualizarEstado(7, $noGuia);
        } elseif ($esDevolucion) {
            $this->model->actualizarEstado(9, $noGuia);
        } else {
            $response = $this->model->actualizarEstado($estadoActualCodigo, $noGuia);
        }

        if ($notificar) {
            //    $this->model->notificar($novedades, $noGuia);
        }
    }
}
