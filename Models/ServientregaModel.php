<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
class ServientregaModel extends Query
{
    private $market;
    public function __construct()
    {
        parent::__construct();
        $this->market = mysqli_connect(HOSTMARKET, USERMARKET, PASSWORDMARKET, DBMARKET);
    }

    public function visualizarGuia($id)
    {
        // URL del servicio web
        $url = "https://swservicli.servientrega.com.ec:5001/api/GuiaDigital/[" . $id . ",'integracion.api.1','54321']";

        // Inicializar cURL
        $ch = curl_init();

        // Configurar opciones de cURL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // Ejecutar la solicitud y obtener la respuesta
        $response = curl_exec($ch);

        // Verificar errores
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        // Cerrar cURL
        curl_close($ch);

        // Decodificar respuesta JSON para obtener la cadena base64 del PDF
        $responseData = json_decode($response, true);
        $base64String = $responseData['archivoEncriptado'];
        $pdfContent = base64_decode($base64String);

        // Verificar si el contenido es PDF
        if (strpos($pdfContent, "%PDF") !== 0) {
            echo "El contenido descargado no es un PDF válido.";
            return;
        }

        // Ruta temporal del archivo PDF
        $tempPdfPath = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tempPdfPath, $pdfContent);

        // Servir el archivo PDF
        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=\"IMPORSUITPRO_" . $id . ".pdf\"");

        readfile($tempPdfPath);

        // Eliminar archivo temporal
        unlink($tempPdfPath);

        // Asegurarse de no enviar más salida
        exit();
    }


    public function actualizar_guia($data)
    {
        $token = "ef4983b54cc73a26d69eac01bca287d0a0f4db5a6eb535d41c29d9ce94a7eb6a";

        $cas = json_encode($data);

        $sql = "INSERT INTO test (cas) VALUES (?)";
        $datas = array($cas);
        $this->insert($sql, $datas);

        /*  $data = json_decode($data,true);  */
        //la fecha llega asi: "fecha_movimiento_novedad": "2024-04-24 12:03:10"
        $guia = $data['guia'];
        $fecha = $data['fecha_movimiento_novedad'];
        $f_movimiento =     date('Y-m-d', strtotime($fecha));
        $h_movimiento =   date('H:i:s', strtotime($fecha));
        $movimiento   = $data['movimiento'];
        $estado       = $data['estado'];
        $ciudad       = $data['ciudad'];
        $observacion1 = $data['observacion1'];
        $observacion2 = $data['observacion2'];
        $observacion3 = $data['observacion3'];

        $sql = "INSERT INTO servi_data (guia, f_movimiento, h_movimiento, movimiento, estado, ciudad, observacion1, observacion2, observacion3) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $data1 = array($guia, $f_movimiento, $h_movimiento, $movimiento, $estado, $ciudad, $observacion1, $observacion2, $observacion3);
        $this->insert($sql, $data1);

        $this->cambioDeEstado($guia, $movimiento);
        if ($movimiento >= "318" && $movimiento <= "351") {
            $this->gestionarNovedad($data, $guia);
        }

        http_response_code(200);
        echo "Recibido correctamente";
    }

    public function anularGuia($id)
    {
        $url = "https://swservicli.servientrega.com.ec:5052/api/guiawebs/['" . $id . "','integracion.api.1','54321']";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Omitir la verificación de SSL (NO recomendado para producción)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // Ejecutar la solicitud y obtener la respuesta
        $response = curl_exec($ch);

        // Verificar si ocurrió algún error
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        // Cerrar la sesión cURL
        curl_close($ch);
        echo $response;
        $this->cambioDeEstado($id, "101");

        if ($response['msj']!='LA GUÍA NO PUEDE SER ANULADA, PORQUE ESTA SIENDO PROCESADA'){
                         
        $sql = "UPDATE facturas_cot SET anulada = 1 WHERE numero_guia = '$id'";
        $result = mysqli_query($this->market, $sql);
        $sql = "DELETE FROM cabecera_cuenta_pagar WHERE guia = '$id'";
        $result = mysqli_query($this->market, $sql);
        }
        
    }

    private function enviar_waller($data, $guia)
    {
        $data = array(
            "noGuia" => $guia, // Asegúrate de que $guia contiene el valor correcto
            "estadoActual" => "Entregado", // Corrige el typo "Entragado"
            "estadoActualCodigo" => 7, // Asegúrate de que esto es un número, no un string
            "novedades" => array(
                array(
                    "codigoTipoNovedad" => 43,
                    "nombreTipoNovedad" => "CONFIRMADO",
                    "codigoDetalleNovedad" => 134,
                    "nombreDetalleNovedad" => "Confirmacion de Guía arribada",
                    "numeroMaximo" => 1,
                    "observacion" => "Guia Grabada Desde Celular",
                    "fechaNovedad" => "2020-12-04T13:42:35"
                )
            )
        );
        $data_string = json_encode($data);

        $url = "https://marketplace.imporsuit.com/sysadmin/api/integracion/Laar/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
    }
    private function enviar_waller2($data, $guia)
    {
        $data = array(
            "noGuia" => $guia, // Asegúrate de que $guia contiene el valor correcto
            "estadoActual" => "Entregado", // Corrige el typo "Entragado"
            "estadoActualCodigo" => 9, // Asegúrate de que esto es un número, no un string
            "novedades" => array(
                array(
                    "codigoTipoNovedad" => 42,
                    "nombreTipoNovedad" => "DEVOLUCION",
                    "codigoDetalleNovedad" => 134,
                    "nombreDetalleNovedad" => "Confirmacion de Guía arribada",
                    "numeroMaximo" => 1,
                    "observacion" => "Guia Grabada Desde Celular",
                    "fechaNovedad" => "2020-12-04T13:42:35"
                ),
                array(
                    "codigoTipoNovedad" => 96,
                    "nombreTipoNovedad" => "DEVOLUCION",
                    "codigoDetalleNovedad" => 134,
                    "nombreDetalleNovedad" => "Confirmacion de Guía arribada",
                    "numeroMaximo" => 1,
                    "observacion" => "Guia Grabada Desde Celular",
                    "fechaNovedad" => "2020-12-04T13:42:35"
                ),
            )
        );
        $data_string = json_encode($data);

        $url = "https://marketplace.imporsuit.com/sysadmin/api/integracion/Laar/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
    }

    private function cambioDeEstado($guia, $estado)
    {
        $sql_update = "UPDATE facturas_cot SET estado_guia_sistema = '$estado' WHERE numero_guia = '$guia'";
        $result_update = mysqli_query($this->market, $sql_update);

        if ($result_update) {
            return true;
        } else {
            return false;
        }
    }

    private function gestionarNovedad($guia, $laar)
    {
        print_r($guia);

        $sql = "SELECT * FROM facturas_cot WHERE numero_guia = '$laar'";
        $result = mysqli_query($this->market, $sql);
        $row = mysqli_fetch_assoc($result);
        $nombreD = $row['nombre'];
        $id_plataforma = $row['id_plataforma'];


        $existe  = "SELECT * FROM novedades WHERE guia_novedad = '$laar'";
        $result_existe = mysqli_query($this->market, $existe);
        $row_existe = mysqli_fetch_assoc($result_existe);
        if ($row_existe) {
            $sql = "UPDATE novedades SET estado_novedad = ?, novedad = ?, solucionada = ? WHERE guia_novedad = ?";
            $stmt = $this->market->prepare($sql);

            $estado_novedad = $guia["movimiento"];
            $novedad = $guia["observacion1"] . " - " . $guia["observacion2"] . " - " . $guia["observacion3"];
            $solucionada = 0;
            $guia_novedad = $laar;

            $stmt->bind_param("isis", $estado_novedad, $novedad, $solucionada, $guia_novedad);

            if ($stmt->execute()) {
                echo "Datos actualizados correctamente.";
            } else {
                echo "Error al actualizar datos: " . $stmt->error;
            }
        } else {
            $sql = "INSERT INTO novedades (guia_novedad, cliente_novedad, estado_novedad, novedad, solucion_novedad, tracking, id_plataforma) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->market->prepare($sql);

            // Revisar si la preparación fue exitosa
            if ($stmt === false) {
                die('MySQL prepare error: ' . $this->market->error);
            }
            $observa = $guia["observacion1"] . " - " . $guia["observacion2"] . " - " . $guia["observacion3"];
            $tracking = "https://servientrega-ecuador.appsiscore.com/app/app-cliente/cons_publica.php?guia=" . $laar . "&Request=Buscar+";
            $gio = "---";
            echo $guia["movimiento"];
            // Vincular los parámetros a la sentencia
            $stmt->bind_param(
                'ssisssi',
                $laar,
                $nombreD,
                $guia["movimiento"],
                $observa,
                $gio,
                $tracking,
                $id_plataforma
            );

            // Ejecutar la consulta
            if ($stmt->execute()) {
                echo "Datos insertados correctamente.";
            } else {
                echo "Error al insertar datos: " . $stmt->error;
            }
        }
    }
}
