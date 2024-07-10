<?

class Speed extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->views->render($this, 'index');
    }

    public function crear()
    {
        $nombreO = $_POST['nombreO'];
        $ciudadO = $_POST['ciudadO'];
        $direccionO = $_POST['direccionO'];
        $telefonoO = $_POST['telefonoO'];
        $referenciaO = $_POST['referenciaO'];

        $nombre = $_POST['nombre'];
        $ciudad = $_POST['ciudad'];
        $direccion = $_POST['direccion'];
        $telefono = $_POST['telefono'];
        $referencia = $_POST['referenciaD'];


        $contiene = $_POST['contiene'];
        $fecha = $_POST['fecha'];
        $numero_factura = $_POST['numero_factura'];
        $url = $_POST['url'];

        $recuado = $_POST['recaudo'];
        $observacion = $_POST['observacion'];

        $monto_factura = $_POST['monto_factura'];


        $response = $this->model->crear($nombreO, $ciudadO, $direccionO, $telefonoO, $referenciaO, $nombre, $ciudad, $direccion, $telefono, $referencia, $contiene, $fecha, $numero_factura, $url, $recuado, $observacion, $monto_factura);
        echo json_encode($response);
    }

    public function descargar($guia)
    {
        $response = $this->model->descargar($guia);
        echo json_encode($response);
    }

    public function anular($guia)
    {
        $response = $this->model->anular($guia);
        echo json_encode($response);
    }

    public function estado($guia)
    {
        $estado = $_POST['estado'];
        $response = $this->model->estado($guia, $estado);
        echo json_encode($response);
    }
}
