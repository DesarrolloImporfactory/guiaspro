<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class SpeedModel extends Query
{
    private $market;
    public function __construct()
    {
        parent::__construct();
        $this->market = mysqli_connect(HOSTMARKET, USERMARKET, PASSWORDMARKET, DBMARKET);
    }

    public function crear($nombreO, $ciudadO, $direccionO, $telefonoO, $referenciaO, $nombre, $ciudad, $direccion, $telefono, $referencia, $contiene, $fecha, $numero_factura, $url, $recaudo, $observacion, $monto_factura, $matriz, $flete_costo)
    {
        $guia = $this->ultimaGuia($matriz);

        $sql = "INSERT INTO guias_speed (nombre_origen, ciudad_origen, direccion_origen, telefono_origen, referencia_origen, nombre_destino, ciudad_destino, direccion_destino, telefono_destino, referencia_destino, contiene, fecha, factura, url, guia, recaudo, observacion, monto_factura, flete_costo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $data = [$nombreO, $ciudadO, $direccionO, $telefonoO, $referenciaO, $nombre, $ciudad, $direccion, $telefono, $referencia, $contiene, $fecha, $numero_factura, $url, $guia, $recaudo, $observacion, $monto_factura, $flete_costo];
        $insert = $this->insert($sql, $data);

        if ($insert != 1) {
            return ["status" => 500, "message" => $insert["message"]];
        }
        $response = $this->generarGuia($guia, $matriz);
        return $response;
    }

    public function ultimaGuia($matriz)
    {
        // Establecer el prefijo en función de la matriz
        if ($matriz == 1) {
            $prefix = "SPD";
        } else if ($matriz == 2) {
            $prefix = "MKL";
        } else {
            $prefix = "DESA";
        }

        // Ejecutar la consulta para obtener el último ID y guía
        $sql = "SELECT MAX(id_speed) AS id, MAX(guia) as guia FROM guias_speed WHERE guia LIKE '$prefix%'";
        $data = $this->select($sql);

        // Verificar si el resultado de la consulta está vacío para la matriz 1
        if ($matriz == 1) {
            if (empty($data[0]['id'])) {
                $guia = "SPD0000001";
            } else {
                $guia = $data[0]['guia'];
                // Verificar si $guia tiene el prefijo 'SPD'
                if (strpos($guia, 'SPD') === 0) {
                    $guia = substr($guia, 3); // Extraer la parte numérica de la cadena
                } else {
                    $guia = '0'; // Si no tiene el prefijo, inicia en 0
                }
                $guia = (int)$guia; // Convertir la parte numérica a un entero
                $guia++; // Incrementar el valor
                $guia = "SPD" . str_pad($guia, 7, "0", STR_PAD_LEFT); // Formatear el número de vuelta a una cadena
            }
        } else
        if ($matriz == 2) {
            if (empty($data[0]['id'])) {
                $guia = "MKL0000001";
            } else {
                $guia = $data[0]['guia'];
                // Verificar si $guia tiene el prefijo 'MKL'
                if (strpos($guia, 'MKL') === 0) {
                    $guia = substr($guia, 3); // Extraer la parte numérica de la cadena
                } else {
                    $guia = '0'; // Si no tiene el prefijo, inicia en 0
                }
                $guia = (int)$guia; // Convertir la parte numérica a un entero
                $guia++; // Incrementar el valor
                $guia = "MKL" . str_pad($guia, 7, "0", STR_PAD_LEFT); // Formatear el número de vuelta a una cadena
            }
        } else
        if ($prefix == 'DESA') {
            if (empty($data[0]['id'])) {
                $guia = "DES0000001";
            } else {
                $guia = $data[0]['guia'];
                // Verificar si $guia tiene el prefijo 'DES'
                if (strpos($guia, 'DES') === 0) {
                    $guia = substr($guia, 3); // Extraer la parte numérica de la cadena
                } else {
                    $guia = '0'; // Si no tiene el prefijo, inicia en 0
                }
                $guia = (int)$guia; // Convertir la parte numérica a un entero
                $guia++; // Incrementar el valor
                $guia = "DES" . str_pad($guia, 7, "0", STR_PAD_LEFT); // Formatear el número de vuelta a una cadena
            }
        } else {
            $guia = "ERR000000001";
        }

        return $guia;
    }



    public function generarGuia($guia, $matriz)
    {

        if ($matriz == 1) {
            $imagen = "https://tiendas.imporsuitpro.com/imgs/Speed.png";
        } else if ($matriz == 2) {
            $imagen = "https://tiendas.imporsuitpro.com/merkalogistic_letters.jpg";
        } else {
            $imagen = "https://tiendas.imporsuitpro.com/merkalogistic_letters.jpg";
        }

        $sql = "SELECT * FROM guias_speed WHERE guia = '$guia'";
        $data = $this->select($sql);
        $data = $data[0];
        $provincia = 'PICHINCHA';
        if ($data['ciudad_destino'] == 'GUAYAQUIL' || $data['ciudad_destino'] == 'SAMBORONDOM' || $data['ciudad_destino'] == 'LA PUNTILLA/GUAYAS') {
            $provincia = 'GUAYAS';
        }

        $html = '
        <!DOCTYPE html>
        <html lang="es">

            <head>
                <meta charset="UTF-8">
                <title>Ticket de Envío</title>
                <style>
            
                @page {
                    size: 100mm 100mm;
                    margin: 0;
                }
        
                body,
                .ticket-container {
                    width: 255.464pt;
                    /* 100mm in points */
                    height: 263.464pt;
                    /* 100mm in points */
                    margin: 0;
                    padding: 5pt;
                    /* Ajusta el padding para ahorrar espacio */
                    overflow: hidden;
                    font-family: Arial, sans-serif;
                    font-size: 8pt;
        
        
                    /* Ajusta el tamaño de la fuente para que el texto encaje */
                }
        
                .ticket-header,
                .ticket-section {
                    text-align: center;
                    margin: 0pt 0;
                    /* Reduce los márgenes */
                }
        
                .ticket-info,
                .ticket-section {
                    clear: both;
                    padding-top: 2pt;
                    /* Reduce el padding */
                }
        
                .ticket-section {
                    border-top: 1px solid #000;
                }
        
                .bold {
                    font-weight: bold;
                }
        
                .text-right {
                    text-align: right;
                }
        
                .text-center {
                    text-align: center;
                }
        
                img {
                    max-width: 80%;
                    /* Reduce el tamaño de las imágenes */
                    height: auto;
                    display: block;
                    margin: auto;
                    /* Centra la imagen */
                }
        
                table {
                    width: 100%;
                }
        
                /* Ajustes adicionales para el texto y los elementos */
                span,
                td {
                    line-height: 1.1;
                    /* Ajusta el interlineado */
                }
            </style>
            </head>

            <body>
                <div class="ticket-container">
                    <div class="ticket-header">
                        <table style="width: 100%;">
                            <tr style="width: 25%;">
                                <td></td>
                            </tr>
                            <tr style="width: 50%;">
                                <td>

                                    <img src="' . $imagen . '" width="100" alt="logo">
                                </td>

                            </tr>
                            <tr style="width: 25%;" class="text-right">
                                <td class="bold" style="font-size: 1.25em;">
                                    ' . $data["guia"] . '
                                </td>
                            </tr>
                        </table>

                    </div>

                    <div class="ticket-info">
                        <table style="width: 100%;">
                            <tr>
                                <td style="width: 50%;">
                                    <span class=" bold">REMITENTE: ' . $data["nombre_origen"] . '</span>
                                    <span>' . $data["direccion_origen"] . " " . $data["referencia_origen"] . '</span>
                                </td>
                                <td style="width: 50%;" class="text-right">
                                    <span class="bold">QUITO </span> <br>
                                    <span>TEL: ' . $data["telefono_origen"] . '</span> <br>
                                    <span>' . $data["telefono_origen"] . '</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="ticket-section">
                    <span class="bold">DESTINO: ' . $data["nombre_destino"] . '</span> <br> 
                        <span> ' . $data["direccion_destino"] . ', ' . $data["referencia_destino"] . '</span><br>
                        <span class="bold">TEL: ' . $data["telefono_destino"] . '</span>
                    
                    </div>

                    <div class="ticket-section">
                        <span>' . $data["observacion"] . '  </span> <br>
                        <span style="font-size: 2em;" class="bold">' . $data["ciudad_destino"] . ' </span> <br>
                        <span class="bold"> ' . $provincia . ' </span>
                        
                    </div>

                    <div class="ticket-section">
                        <span> Peso:  2 KG<br></span>
                        <span class="bold">Contenido: </span><br>  <span style="font-size: 0.75rem; max-width: 60px;">' . $data["contiene"] . '</span><br>
                        <span>Valor asegurado: $0.00</span>
                    
                    </div>
        ';
        if ($data['recaudo'] == '1') {

            $recaudado = '
            <div class="ticket-section text-center">
            <br> <span class="bold">VALOR A COBRAR $' . $data["monto_factura"] . '</span><br>        
            </div>
            ';
        } else {
            $recaudado = ' <div class="ticket-section text-center">
            <br> <span class="bold">GUIA SIN RECAUDO</span><br>        
            </div>';
        }

        $final = '
                </div>
            </body>

        </html>
        ';

        $html = $html . $recaudado . $final;

        $sql = "INSERT INTO `visor`(`html`, `guia`) VALUES (?,?)";
        $data = [$html, $guia];

        $insert = $this->insert($sql, $data);

        if ($insert == 1) {
            return ["status" => 200, "message" => "Guia generada", "guia" => $guia];
        } else {
            return ["status" => 500, "message" => "Error al generar guia"];
        }
    }

    public function descargar($guia)
    {
        $sql = "SELECT * FROM visor WHERE guia = '$guia'";
        $data = $this->select($sql);
        $data = $data[0];

        if (!empty($data)) {
            $html = $data['html'];
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'Arial');
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper(array(0, 0, 4.937 * 72, 4.937 * 72), 'portrait');
            $dompdf->render();
            $dompdf->stream($guia . ".pdf", array("Attachment" => 1));
        } else {
            echo "No se encontro la guia";
        }
    }

    public function anular($guia)
    {
        $sql = "UPDATE guias_speed SET estado = 8 WHERE guia = ?";
        $update = $this->update($sql, [$guia]);

        $sql = "UPDATE facturas_cot SET estado_guia_sistema = '8', anulada = 1 WHERE numero_guia = '$guia'";
        $update = mysqli_query($this->market, $sql);

        $sql = "DELETE FROM cabecera_cuenta_pagar WHERE guia = '$guia'";
        $delete = mysqli_query($this->market, $sql);

        return ["status" => 200, "message" => "Guia anulada"];
    }

    // Función para procesar la guía y actualizar registros
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

    public function estado($guia, $estado)
    {
        $sql = "UPDATE guias_speed SET estado = ? WHERE guia = ?";
        $update = $this->update($sql, [$estado, $guia]);

        $sql = "UPDATE facturas_cot SET estado_guia_sistema = $estado WHERE numero_guia = '$guia'";
        $update = mysqli_query($this->market, $sql);

        $sql = "UPDATE cabecera_cuenta_pagar SET estado_guia = $estado WHERE guia = '$guia' ";
        $update = mysqli_query($this->market, $sql);

        if ($estado == 9) {
            $sql = "UPDATE cabecera_cuenta_pagar SET estado_guia = 9, monto_recibir = ((precio_envio + full) * -1), valor_pendiente =  ((precio_envio + full) * -1) WHERE guia = '$guia' ";
            $update = mysqli_query($this->market, $sql);
        }
        if ($estado > 2) {
            // Obtener datos de la factura
            $sql = "SELECT * FROM facturas_cot WHERE numero_guia = ?";
            $stmt = $this->market->prepare($sql);
            $stmt->bind_param("s", $guia);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if ($data) {
                $id_plataforma = $data['id_plataforma']; // Vendedor
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
        return ["status" => 200, "message" => "Estado actualizado"];
    }
}
