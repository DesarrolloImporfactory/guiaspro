<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

class PDF extends Fpdi
{
    private $contenido;

    public function __construct($orientation, $unit, $size, $unicode, $encoding, $diskcache, $contenido)
    {
        parent::__construct($orientation, $unit, $size, $unicode, $encoding, $diskcache);
        $this->contenido = $contenido;
    }

    public function Header()
    {
        // Add a table at the beginning of the document
        $this->SetFont('helvetica', '', 6);
        $tbl = '<table cellspacing="0" cellpadding="1" border="1">
                    <tr>
                        <th><strong>CONTENIDO:</strong></th>
                    </tr>
                    <tr>
                        <td>' . $this->contenido . '</td>
                    </tr>
                </table>';
        $this->writeHTML($tbl, true, false, false, false, '');
        $this->SetY($this->GetY() + 10); // Adjust Y position for the rest of the document content
    }
}

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
        // Obtener contenido de la guia
        $sql = "SELECT * FROM facturas_cot WHERE numero_guia = '$id'";
        $result = mysqli_query($this->market, $sql);
        $row = mysqli_fetch_assoc($result);

        $contenido = $row['contiene'];

        // URL del servicio web
        $url = "https://swservicli.servientrega.com.ec:5001/api/GuiaDigital/[" . $id . ",'imp.1793168264001','Ecuador24']";

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

        // Crear nuevo documento PDF con tamaño personalizado de 100 x 148 mm
        $pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, [100, 148], true, 'UTF-8', false, $contenido);

        // Agregar una página
        $pdf->AddPage();

        // Importar el PDF existente descargado
        $pdf->setSourceFile($tempPdfPath);
        $tplId = $pdf->importPage(1);

        // Ajustar la posición y el tamaño de la página importada para que se ajuste debajo de la tabla
        $pdf->useTemplate($tplId, 5, 0, 90); // Ajustar la posición X, Y y el tamaño según sea necesario

        // Salida del nuevo PDF
        $pdf->Output('SERVIENTREGA_' . $id . '.pdf', 'I');

        // Eliminar archivo temporal
        unlink($tempPdfPath);
    }

    public function visualizarGuias($id, $nombre)
    {
        // URL del servicio web
        $url = "https://swservicli.servientrega.com.ec:5001/api/GuiaDigital/[" . $id . ",'imp.1793168264001','Ecuador24']";

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
        header("Content-Disposition: attachment; filename=\"$nombre" . $id . ".pdf\"");

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

        $sql = "INSERT INTO test (cas) VALUES ($cas)";
        $datas = array($cas);

        $respuesta = mysqli_query($this->market, $sql);

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
        if ($movimiento >= "320" && $movimiento <= "351") {
            $this->gestionarNovedad($data, $guia);
        }

        http_response_code(200);
        echo "Recibido correctamente";

        $this->webhooktelefono($guia);
    }

    public function webhooktelefono($guia)
    {
        $id_factura = $this->findIdFactura($guia);
        $ch = curl_init();
        $url = "https://new.imporsuitpro.com/speed/automatizador";
        //formdata 
        $data = array('id_factura' => $id_factura);
        $data = http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        echo $response;
    }

    public function findIdFactura($guia)
    {
        $sql = "SELECT id_factura FROM facturas_cot WHERE numero_guia = '$guia'";
        $result = mysqli_query($this->market, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['id_factura'];
    }

    public function anularGuia($id)
    {
        $url = "https://swservicli.servientrega.com.ec:5052/api/guiawebs/['" . $id . "','imp.1793168264001','Ecuador24']";
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

        $response = json_decode($response, true);
        if ($response['msj'] != 'LA GUÍA NO PUEDE SER ANULADA, PORQUE ESTA SIENDO PROCESADA') {
            $this->cambioDeEstado($id, "101");

            $sql = "UPDATE facturas_cot SET anulada = 1 WHERE numero_guia = '$id'";
            $result = mysqli_query($this->market, $sql);
            $sql = "DELETE FROM cabecera_cuenta_pagar WHERE guia = '$id'";
            $result = mysqli_query($this->market, $sql);
            echo json_encode([
                "status" => "200",
                "message" => "Guia anulada correctamente"
            ]);
        } else {
            echo json_encode([
                "status" => "400",
                "message" => "La guia no puede ser anulada, porque esta siendo procesada"
            ]);
        }
    }

    private function cambioDeEstado($guia, $estado)
    {
        $sql_update = "UPDATE facturas_cot SET estado_guia_sistema = '$estado' WHERE numero_guia = '$guia'";
        $result_update = mysqli_query($this->market, $sql_update);
        $this->bitacora($guia, $estado, "Servientrega");

        if ($estado >=  "400" && $estado <= "499") {
            $estado = 7;
        } else if ($estado >= "500" && $estado <= "599") {
            $estado = 9;
        }

        $sql_update = "UPDATE cabecera_cuenta_pagar SET estado_guia = '$estado' WHERE guia = '$guia'";
        if ($estado == 9) {
            $valor_pendiente = $this->obtenerValorPendiente($guia);
            if ($valor_pendiente != 0) {
                $sql_update = "UPDATE cabecera_cuenta_pagar SET valor_pendiente =  ((precio_envio + full) * -1), monto_recibir =  ((precio_envio + full) * -1), estado_guia = 9 WHERE guia = '$guia'";
                $result_update = mysqli_query($this->market, $sql_update);
            } else {
                $result_update = mysqli_query($this->market, $sql_update);
            }
        }
        if ($estado > 101) {

            // Obtener datos de la factura
            $sql = "SELECT * FROM facturas_cot WHERE numero_guia = ?";
            $stmt = $this->market->prepare($sql);
            $stmt->bind_param("s", $guia);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if ($data) {
                $id_plataforma = $data['id_plataforma']; // Vendedor
                if ($id_plataforma == 2324 || $id_plataforma == 3031) {
                    //romper la funcion
                    return;
                }
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

        $result_update = mysqli_query($this->market, $sql_update);

        echo mysqli_error($this->market);
        if ($result_update) {
            return true;
        } else {
            return false;
        }
    }

    public function obtenerValorPendiente($guia)
    {
        $sql = "SELECT valor_pendiente FROM cabecera_cuenta_pagar WHERE guia = '$guia'";
        $result = mysqli_query($this->market, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['valor_pendiente'];
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

    public function test($data)
    {
        echo "hola";

        $data = json_encode($data);
        $sql = "INSERT INTO test (cas) VALUES ('$data')";
        $datas = array($data);
        $respuesta = mysqli_query($this->market, $sql);
        echo mysqli_error($this->market);
    }

    public function masivo()
    {
        $sql = "SELECT * FROM test";
        $data = mysqli_query($this->market, $sql);
        $data = mysqli_fetch_all($data, MYSQLI_ASSOC);

        foreach ($data as $key => $value) {
            echo $value["cas"];
            $curl = curl_init();
            $url = "https://guias.imporsuitpro.com/Servientrega";
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($value["cas"]));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);
            curl_close($curl);
            echo $response;
        }
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

    public function validarGuias()
    {
        $sql = "SELECT guia, estado_guia FROM `cabecera_cuenta_pagar` where guia REGEXP '^[0-9]+$' and estado_guia >=1 and estado_guia < 400 and visto= 0;";
        $result = mysqli_query($this->market, $sql);
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);

        foreach ($data as $key => $value) {
            $guia = $value['guia'];
            $response = $this->dataServi($guia);

            if ($response) $this->validarServientrega($response, $guia);
        }
    }

    public function validarGuia($id)
    {
        $sql = "SELECT guia FROM cabecera_cuenta_pagar WHERE guia = '$id'";
        $result = mysqli_query($this->market, $sql);
        $data = mysqli_fetch_assoc($result);

        if ($data) {
            $guia = $data['guia'];
            $response = $this->dataServi($guia);

            if ($response) $this->validarServientrega($response, $guia);
        } else {
            echo "Guia no valida";
        }
    }

    private function validarServientrega($data, $guia)
    {
        $data = strtoupper($data);
        if (str_contains($data, 'DEVOLUCION AL REMITENTE')) {
            $this->cambioDeEstado($guia, 500);
            echo "GUIA: $guia - DEVOLUCION AL REMITENTE\n";
            return null;
        } elseif (str_contains($data, 'REPORTADO ENTREGADO')) {
            $this->cambioDeEstado($guia, 400);
            echo "GUIA: $guia - REPORTADO ENTREGADO\n";
            return null;
        } else {
            echo "GUIA: $guia - NO VALIDA\n";
        }
    }

    public function dataServi($guia)
    {
        // URL del servicio web SOAP
        $wsdlUrl = 'https://servientrega-ecuador.appsiscore.com:443/app/ws/server_trazabilidad.php?wsdl';
        // Configuración del cliente SOAP
        $options = [
            'location' => 'https://servientrega-ecuador.appsiscore.com/app/ws/server_trazabilidad.php',
            'uri' => 'https://servientrega-ecuador.appsiscore.com/app/ws/',
            'trace' => true,
            'exceptions' => true
        ];
        $client = new SoapClient(null, $options);
        // Luego llamas al método con __soapCall, pasando 'ConsultarGuiaImagen' y los parámetros adecuados.

        $params = [
            'guia' => $guia,
        ];

        try {
            // Realizar la llamada al método del servicio web SOAP
            $response = $client->__soapCall('ConsultarGuiaImagen', $params);
            // Obtener la respuesta del servicio web
            $result = $response;
            // Procesar la respuesta según sea necesario
            return $result;
        } catch (SoapFault $e) {
            // Capturar errores de la solicitud SOAP
            echo 'Error: ' . $e->getMessage();
        }
    }
}
