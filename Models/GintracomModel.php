<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
class GintracomModel extends Query
{
    private $market;
    public function __construct()
    {
        parent::__construct();
        $this->market = mysqli_connect(HOSTMARKET, USERMARKET, PASSWORDMARKET, DBMARKET);
        if (!$this->market) {
            die("Connection failed: " . mysqli_connect_error());
        }
    }
    public function webhook($datas)
    {
        //  $data = json_decode($data, true);
        echo $datas;
        $query = "INSERT INTO gintracom_webhook (valor) VALUES ('$datas')";
        $data = array($datas);
        $quers = mysqli_query($this->market, $query);

        /*         $this->insert($query, $data);
         */        //
        $datos = json_decode($datas, true);
        if (isset($datos["data"]) && is_array($datos["data"])) {
            foreach ($datos["data"] as $dato) {
                $guia = $dato["guia"];
                $sql = "SELECT * FROM facturas_cot where numero_guia = '$guia'";
                $data2 = mysqli_query($this->market, $sql);
                $data2 = mysqli_fetch_all($data2, MYSQLI_ASSOC);
                print_r($data2);
                //actualiza market
                if (count($data) > 0) {
                    $sql = "UPDATE facturas_cot SET estado_guia_sistema = '" . $dato["estado"] . "' WHERE numero_guia = '" . $guia . "'";
                    $response = mysqli_query($this->market, $sql);
                    $sql = "UPDATE cabecera_cuenta_pagar SET estado_guia = '" . $dato["estado"] . "' WHERE guia = '" . $guia . "'";
                    $response = mysqli_query($this->market, $sql);
                }
                $plataforma = $data2[0]["id_plataforma"];
                $nombreD = $data2[0]["nombre"];
                print_r($dato["novedades"]);
                if (isset($dato["novedades"])) {
                    if (empty($dato["novedades"]["nombreNovedad"]) || $dato["novedades"]["nombreNovedad"] == "null" || $dato["novedades"]["nombreNovedad"] == null) {
                    } else {
                        $sql = "INSERT INTO novedades (guia_novedad, cliente_novedad, estado_novedad, novedad, tracking, fecha, id_plataforma) VALUES ( '" . $guia . "', '" . $nombreD . "', '" . $dato["estado"] . "', '" . $dato["novedades"]["nombreNovedad"] . "', 'https://ec.gintracom.site/web/site/tracking', '" . $dato["novedades"]["fechaNovedad"] . "', '" . $plataforma . "')";
                        $response = mysqli_query($this->market, $sql);
                    }
                }
            }
        }
    }

    public function noaplica($data)
    {
        $query = "INSERT INTO gintracom_webhook (json) VALUES (?)";
        $data = array($data);
        $this->insert($query, $data);
    }


    public function traqueo($id)
    {
        $url = "https://ec.gintracom.site/web/import-suite/tracking";
        $response = $this->enviar_datos($url, $id);
        echo $response;
    }

    public function estado($id)
    {
        $url = "https://ec.gintracom.site/web/import-suite/estado";
        $response = $this->enviar_datos($url, $id);
        echo $response;
    }

    public function labels($id)
    {
        $url = "https://ec.gintracom.site/web/import-suite/label";
        $response = $this->enviar_datos($url, $id);
        $server_url =  "../temp2.pdf";

        file_put_contents($server_url, $response);
        //abrir el archivo
        header("Content-type: application/pdf");
        header("Content-Disposition: attachment; filename=\"IMPORSUITPRO_GINTRACOM" . $id . ".pdf\"");
        readfile($server_url);
    }
    public function anular($id)
    {
        $url = "https://ec.gintracom.site/web/import-suite/anular";
        $response = $this->enviar_datos($url, $id);

        $sql = "UPDATE facturas_cot SET estado_guia_sistema = '12', anulada =1 WHERE  numero_guia = '" . $id . "'";
        $response = mysqli_query($this->market, $sql);

        $sql = "DELETE FROM cabecera_cuenta_pagar WHERE guia = '" . $id . "'";
        $response = mysqli_query($this->market, $sql);


        echo $response;
    }

    private function enviar_datos($url, $id)
    {
        //Basic Auth
        $username = 'importsuite';
        $password = "ab5b809caf73b2c1abb0e4586a336c3a";

        $data = array("guia" => $id);
        $data_string = json_encode($data);
        //inicia curl
        $ch = curl_init($url);
        //configura las opciones de curl
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string)
            )
        );
        //ejecuta curl
        $result = curl_exec($ch);
        //cierra curl
        curl_close($ch);
        //decodifica el resultado
        //$response = json_decode($result, true);
        //verifica si hay error
        $response = $result;
        if (isset($response['error'])) {
            echo $response['error'];
        }

        return $response;
    }

    public function masivo()
    {
        $sql = "SELECT * FROM gintracom_webhook";
        $data = mysqli_query($this->market, $sql);
        $data = mysqli_fetch_all($data, MYSQLI_ASSOC);
        foreach ($data as $dato) {
            $this->webhook($dato["valor"]);
            //cada 2 segundos
            sleep(2);
        }
    }
}
