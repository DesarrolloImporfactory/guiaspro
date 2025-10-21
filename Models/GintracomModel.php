<?php
require_once 'Class/AnotherServer.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
class GintracomModel extends Query
{
    private $market;
    private $anotherServer;
    public function __construct()
    {
        parent::__construct();
        $this->market = mysqli_connect(HOSTMARKET, USERMARKET, PASSWORDMARKET, DBMARKET);
        if (!$this->market) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $this->anotherServer = AnotherServer::getInstance();
        $config = [
            "host" => $_ENV["DB_ANOTHER_HOST"],
            "database" => $_ENV["DB_ANOTHER_DATABASE"],
            "username" => $_ENV["DB_ANOTHER_USERNAME"],
            "password" => $_ENV["DB_ANOTHER_PASSWORD"]
        ];
        $this->anotherServer->configure("chatcenter", $config);
    }
    public function webhook($datas)
    {
        //  $data = json_decode($data, true);
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
                //actualiza market
                if (count($data) > 0) {
                    if ($dato["estado"] > 2) {
                        // Obtener datos de la factura
                        $sql = "SELECT * FROM facturas_cot WHERE numero_guia = ?";
                        $stmt = $this->market->prepare($sql);
                        $stmt->bind_param("s", $guia);
                        $stmt->execute();
                        $data = $stmt->get_result()->fetch_assoc();
                        $dete = $data;

                        var_dump($dete);

                        if ($data) {
                            $id_plataforma = $data['id_plataforma']; // Vendedor
                            // si la plataforma vendedora es 3031 o 2324 saltar todo
                            var_dump($data);
                            $id_factura = $data['id_factura'];

                            var_dump($id_factura);
                            $s =  $this->anotherServer->update("chatcenter", "UPDATE clientes_chat_center SET estado_factura = ? WHERE id_factura = ?", [$dato["estado"], $id_factura]);
                            var_dump($s);

                            $id_propietario = $data['id_propietario']; // Proveedor

                            // Obtener datos del vendedor
                            $sql = "SELECT refiere FROM plataformas WHERE id_plataforma = ?";
                            $stmt = $this->market->prepare($sql);
                            $stmt->bind_param("s", $id_plataforma);
                            $stmt->execute();
                            $vendedorData = $stmt->get_result()->fetch_assoc();
                            $refiere_vendedor = $vendedorData['refiere'] ?? null;

                            // Obtener datos del proveedor
                            $sql = "SELECT refiere FROM plataformas WHERE id_plataforma = ?";
                            $stmt = $this->market->prepare($sql);
                            $stmt->bind_param("s", $id_propietario);
                            $stmt->execute();
                            $proveedorData = $stmt->get_result()->fetch_assoc();
                            $refiere_proveedor = $proveedorData['refiere'] ?? null;

                            // Caso 1: Vendedor y Proveedor son referidos
                            if (!empty($refiere_vendedor) && !empty($refiere_proveedor)) {
                                // Verificar si la tienda que refiere al proveedor es 1188
                                if ($refiere_proveedor == 1188) {
                                    // Añadir al que refiere al vendedor y al proveedor
                                    $this->procesarGuia($guia, $refiere_vendedor);
                                    $this->procesarGuia($guia, $refiere_proveedor);
                                } else {
                                    // Añadir solo al que refiere al vendedor
                                    $this->procesarGuia($guia, $refiere_vendedor);
                                }
                            } elseif (!empty($refiere_vendedor)) {
                                // Caso 2: Solo el vendedor es referido
                                $this->procesarGuia($guia, $refiere_vendedor);
                            } elseif (!empty($refiere_proveedor)) {
                                // Caso 3: Solo el proveedor es referido
                                $this->procesarGuia($guia, $refiere_proveedor);
                            }
                            // Caso 4: Si ninguno es referido, no hacer nada

                        }
                    }

                    if ($dato["estado"] == 9 || $dato["estado"] == 13) {
                        $sql = "UPDATE facturas_cot SET estado_guia_sistema = '" . $dato["estado"] . "' WHERE numero_guia = '" . $guia . "'";
                        $response = mysqli_query($this->market, $sql);

                        $fueAcreditada = "SELECT * FROM cabecera_cuenta_pagar WHERE guia = '$guia'";
                        $fueAcreditada = mysqli_query($this->market, $fueAcreditada);
                        $fueAcreditada = mysqli_fetch_all($fueAcreditada, MYSQLI_ASSOC);
                        $visto = $fueAcreditada[0]["visto"];

                        if ($visto == 0) {
                            $sql = "UPDATE cabecera_cuenta_pagar SET estado_guia = '" . $dato["estado"] . "', monto_recibir=precio_envio*-1, valor_pendiente=precio_envio*-1 WHERE guia = '" . $guia . "'";
                            $response = mysqli_query($this->market, $sql);
                        } else {
                            $sql = "UPDATE cabecera_cuenta_pagar SET estado_guia = '" . $dato["estado"] . "' WHERE guia = '" . $guia . "'";
                            $response = mysqli_query($this->market, $sql);
                        }
                    } else {
                        $sql = "UPDATE facturas_cot SET estado_guia_sistema = '" . $dato["estado"] . "' WHERE numero_guia = '" . $guia . "'";
                        $response = mysqli_query($this->market, $sql);

                        $sql = "UPDATE cabecera_cuenta_pagar SET estado_guia = '" . $dato["estado"] . "' WHERE guia = '" . $guia . "'";
                        $response = mysqli_query($this->market, $sql);
                    }
                    $this->bitacora($guia, $dato["estado"], $dato["transportadora"]);
                }
                $plataforma = $data2[0]["id_plataforma"];
                $nombreD = $data2[0]["nombre"];
                print_r($dato["novedades"]);
                if (isset($dato["novedades"])) {
                    if (empty($dato["novedades"]["nombreNovedad"]) || $dato["novedades"]["nombreNovedad"] == "null" || $dato["novedades"]["nombreNovedad"] == null) {
                    } else {
                        $sql = "SELECT * FROM novedades WHERE guia_novedad = '$guia'";
                        $data3 = mysqli_query($this->market, $sql);
                        $data3 = mysqli_fetch_all($data3, MYSQLI_ASSOC);
                        if (count($data3) > 0) {
                            $sql = "UPDATE novedades SET estado_novedad = '" . $dato["novedades"]["codigoNovedad"] . "', novedad = '" . $dato["novedades"]["nombreNovedad"] . "', fecha = '" . $dato["novedades"]["fechaNovedad"] . "', solucionada=0  WHERE guia_novedad = '" . $guia . "'";
                            $response = mysqli_query($this->market, $sql);
                            echo    $sql;
                            echo mysqli_error($this->market);

                            $id_factura = $data['id_factura'];
                            $texto = '{
                                "novedad": "' . $dato["novedades"]["nombreNovedad"] . '",
                                "terminada": 0,
                                "id_novedad": "' . $data3[0]["id_novedad"] . '",
                                "solucionada": "0"
                            }';
                            $this->anotherServer->update("chatcenter", "UPDATE clientes_chat_center SET novedad_info = ? WHERE id_factura = ?", [$texto, $id_factura]);
                        } else {
                            $sql = "INSERT INTO novedades (guia_novedad, cliente_novedad, estado_novedad, novedad, tracking, fecha, id_plataforma) VALUES ( '" . $guia . "', '" . $nombreD . "', '" . $dato["novedades"]["codigoNovedad"] . "', '" . $dato["novedades"]["nombreNovedad"] . "', 'https://ec.gintracom.site/web/site/tracking', '" . $dato["novedades"]["fechaNovedad"] . "', '" . $plataforma . "')";
                            $response = mysqli_query($this->market, $sql);
                        }
                    }
                } else {
                    $texto = '{
                        "novedad": null,
                        "terminada": null,
                        "id_novedad": null,
                        "solucionada": null
                    }';
                    $this->anotherServer->update("chatcenter", "UPDATE clientes_chat_center SET novedad_info = ? WHERE id_factura = ?", [$texto, $id_factura]);
                }

                // si el estado es 7 ,8 o 9
                if ($dato["estado"] == 7 || $dato["estado"] == 8 || $dato["estado"] == 9) {
                    $this->terminar_novedad($guia);
                }
            }
        }

        $this->webhooktelefono($guia);
    }

    public function terminar_novedad($guia)
    {
        $sql = "UPDATE novedades SET terminado = 1 WHERE guia_novedad = '$guia'";
        echo $sql;
        $response =  mysqli_query($this->market, $sql);
        echo mysqli_error($this->market);
        print_r($response);
        $sql = "DELETE FROM `detalle_novedad` where guia_novedad = '$guia' ";
        $response =  mysqli_query($this->market, $sql);
        echo mysqli_error($this->market);
    }


    function procesarGuia($guia, $refiere)
    {
        // Verificar si la guía ya existe para esta plataforma
        $sql = "SELECT 1 FROM cabecera_cuenta_referidos WHERE guia = ? AND id_plataforma = ?";
        $stmt = $this->market->prepare($sql);
        $stmt->bind_param("ss", $guia, $refiere);
        $stmt->execute();
        $exists = $stmt->get_result();

        if ($exists->num_rows == 0) {
            // Añadir nuevo registro a cabecera_cuenta_referidos
            $sql = "REPLACE INTO cabecera_cuenta_referidos (guia, monto, fecha, id_plataforma) VALUES (?, 0.3, NOW(), ?)";
            $stmt = $this->market->prepare($sql);
            $stmt->bind_param("ss", $guia, $refiere);
            $stmt->execute();

            // Actualizar el saldo en billetera_referidos
            $sql = "UPDATE billetera_referidos SET saldo = saldo + 0.3 WHERE id_plataforma = ?";
            $stmt = $this->market->prepare($sql);
            $stmt->bind_param("s", $refiere);
            $stmt->execute();

            // Obtener saldo actual para el historial
            $sql = "SELECT saldo FROM billetera_referidos WHERE id_plataforma = ?";
            $stmt = $this->market->prepare($sql);
            $stmt->bind_param("s", $refiere);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            $nuevo_saldo = $data['saldo'];

            // Insertar en el historial de referidos
            $sql = "INSERT INTO historial_referidos (id_billetera, motivo, monto, previo, fecha) 
                 SELECT id_billetera, ?, 0.3, saldo, NOW() 
                 FROM billetera_referidos WHERE id_plataforma = ?";
            $stmt = $this->market->prepare($sql);
            $motivo = "Referido por guia $guia";
            $stmt->bind_param("ss", $motivo, $refiere);
            $stmt->execute();
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
        $server_url =  "../temp21.pdf";

        file_put_contents($server_url, $response);


        //abrir el archivo
        header("Content-type: application/pdf");
        header("Content-Disposition: attachment; filename=\"GINTRACOM" . $id . ".pdf\"");
        readfile($server_url);
    }

    public function getIdFactura($guia)
    {
        $sql = "SELECT id_factura FROM facturas_cot WHERE numero_guia = ?";
        $stmt = $this->market->prepare($sql);
        $stmt->bind_param("s", $guia);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        return $data['id_factura'];
    }

    public function webhooktelefono($guia)
    {
        $id_factura = $this->getIdFactura($guia);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://new.imporsuitpro.com/speed/automatizador");
        //form data
        $data = array(
            'id_factura' => $id_factura,
            'guia' => $guia
        );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        echo $output;
    }


    public function anular($id)
    {
        $url = "https://ec.gintracom.site/web/import-suite/anular";
        $response = $this->enviar_datos($url, $id);


        $sql = "UPDATE facturas_cot SET estado_guia_sistema = '12', anulada =1 WHERE  numero_guia = '" . $id . "'";
        $response = mysqli_query($this->market, $sql);

        $sql = "DELETE FROM cabecera_cuenta_pagar WHERE guia = '" . $id . "'";
        $response = mysqli_query($this->market, $sql);

        echo json_encode([
            "status" => 200,
            "message" => "Guia anulada correctamente"
        ]);
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
