<?php

class Servientrega extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $this->model->test($data);
        if (!empty($data)) {
            $this->model->actualizar_guia($data);
        } else {
            http_response_code(400);
            echo "Error: No se recibieron datos";
        }
    }

    public function Guia($id)
    {
        //verificar id sea numerico}
        if (is_numeric($id)) {
            $this->model->visualizarGuia($id);
        } else {
            echo "Guia no valida";
        }
    }

    public function Anular($id)
    {

        //verificar id sea numerico}
        if (is_numeric($id)) {
            $this->model->anularGuia($id);
        } else {
            echo "Guia no valida";
        }
    }

    public function masivo()
    {
        $this->model->masivo();
    }

    public function obtenerValorPendiente()
    {
        $guia = $_POST['guia'];
        echo $this->model->obtenerValorPendiente($guia);
    }

    public function validarGuias()
    {
        $this->model->validarGuias();
    }

    public function validarGuia($id)
    {
        $this->model->validarGuia($id);
    }
}
