<?php
class SpeedModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    public function crear($nombreO, $ciudadO, $direccionO, $telefonoO, $referenciaO, $nombre, $ciudad, $direccion, $telefono, $referencia, $contiene, $fecha, $numero_factura, $url, $recaudo, $observacion, $monto_factura)
    {
        $guia = $this->ultimaGuia();

        $sql = "INSERT INTO guias_speed (nombre_origen, ciudad_origen, direccion_origen, telefono_origen, referencia_origen, nombre_destino, ciudad_destino, direccion_destino, telefono_destino, referencia_destino, contiene, fecha, numero_factura, url, guia, recaudo, observacion, monto_factura) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $data = [$nombreO, $ciudadO, $direccionO, $telefonoO, $referenciaO, $nombre, $ciudad, $direccion, $telefono, $referencia, $contiene, $fecha, $numero_factura, $url, $guia, $recaudo, $observacion, $monto_factura];
        $insert = $this->insert($sql, $data);

        $this->generarGuia($guia);
    }

    public function ultimaGuia()
    {
        $sql = "SELECT MAX(id_speed) AS id FROM guias_speed;";
        $data = $this->select($sql);

        if (empty($data[0])) {
            $guia = "SPD0000001";
        } else {
            $guia = $data[0]["id_speed"];
            $guia = substr($guia, 3);
            $guia = (int)$guia;
            $guia++;
            $guia = "SPD" . str_pad($guia, 7, "0", STR_PAD_LEFT);
        }
        return $guia;
    }

    public function generarGuia($guia)
    {
        $imagen = "https://tiendas.imporsuitpro.com/imgs/SPEED.jpg";

        $sql = "SELECT * FROM guias_speed WHERE guia = '$guia'";
        $data = $this->select($sql);
        $data = $data[0];


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
                        <span> ' . $data["direccion_destino"] . $data["referencia_destino"] . '</span><br>
                        <span class="bold">TEL: ' . $data["telefono_destino"] . '</span>
                    
                    </div>

                    <div class="ticket-section">
                        <span>' . $guia["observacion"] . '  </span> <br>
                        <span style="font-size: 2em;" class="bold">' . $data["ciudad_destino"] . ' </span> <br>
                        <span class="bold"> PICHINCHA </span>
                        
                    </div>

                    <div class="ticket-section">
                        <span> Peso:  2 KG<br></span>
                        <span class="bold">Contenido: </span><br>  <span style="font-size: 0.75rem; max-width: 60px;">' . $data["contiene"] . '</span><br>
                        <span>Valor asegurado: $0.00</span>
                    
                    </div>

                    <div class="ticket-section text-center">
                        <br> <span class="bold">VALOR A COBRAR $' . $data["monto_factura"] . '</span><br>        
                    </div>

                </div>
            </body>

        </html>
        ';

        $sql = "INSERT INTO `visor`(`html`, `guia`) VALUES (?,?)";
        $data = [$html, $guia];

        $insert = $this->insert($sql, $data);

        if ($insert == 1) {
            return ["status" => 200, "message" => "Guia generada", "guia" => $guia];
        } else {
            return ["status" => 500, "message" => "Error al generar guia"];
        }
    }
}
