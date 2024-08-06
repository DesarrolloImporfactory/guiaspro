<?php
class LaarModel extends Query
{
    private $market;
    public function __construct()
    {
        parent::__construct();
        $this->market = mysqli_connect(HOSTMARKET, USERMARKET, PASSWORDMARKET, DBMARKET);
    }

    public function capturador($json)
    {
        $sql = "INSERT INTO laar (json) VALUES ($json)";
        $response =  mysqli_query($this->market, $sql);
    }

    public function actualizarEstado($estado, $guia)
    {
        $sql = "UPDATE facturas_cot set estado_guia_sistema = '$estado' WHERE numero_guia = '$guia' ";
        $response =  mysqli_query($this->market, $sql);
        $update = "UPDATE cabecera_cuenta_pagar set estado_guia = '$estado' WHERE guia = '$guia' ";
        $response =  mysqli_query($this->market, $update);
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

    public function notificar($novedades, $guia)
    {
        $sql = "SELECT id_plataforma FROM facturas_cot WHERE numero_guia = '$guia' ";
        $response = mysqli_query($this->market, $sql);
        $data = mysqli_fetch_assoc($response);
        $id_plataforma = $data['id_plataforma'];

        $avisar = false;
        $nombre = "";
        foreach ($novedades as $novedad) {
            if ($novedad['codigoTipoNovedad'] == 42 || $novedad['codigoTipoNovedad'] == 43 || $novedad['codigoTipoNovedad'] == 92 || $novedad['codigoTipoNovedad'] == 96) {
                $avisar = false;
                break;
            }

            $sql = "SELECT * FROM detalle_novedad WHERE guia_novedad = '$guia' AND codigo_novedad = '" . $novedad['codigoTipoNovedad'] . "' ";
            $response = mysqli_query($this->market, $sql);
            $response = mysqli_fetch_assoc($response);
            print_r($response);

            if (count($response) == 0) {
                echo "entre";

                $avisar = true;
                $codigo = $novedad["codigoTipoNovedad"];
                $nombre = $novedad['nombreDetalleNovedad'];
                $detalle = $novedad['nombreTipoNovedad'];
                $observacion = $novedad['observacion'];
            }
            $sql = "INSERT INTO detalle_novedad (codigo_novedad, guia_novedad, nombre_novedad, detalle_novedad, observacion, id_plataforma) VALUES ('$codigo', '$guia', '$nombre', '$detalle', '$observacion', '$id_plataforma')";
            $response = mysqli_query($this->market, $sql);

            print_r($response);
        }

        echo $avisar;
        if ($avisar) {

            if (strpos($guia, 'IMP') == 0) {
                $tracking = "https://fenix.laarcourier.com/Tracking/Guiacompleta.aspx?guia=" . $guia;
            } else if (strpos($guia, 'I00') == 0) {
                $tracking = "https://ec.gintracom.site/web/site/tracking";
            } else if (is_numeric($guia)) {
                $tracking = "https://www.servientrega.com.ec/Tracking/?guia=" . $guia . "&tipo=GUI";
            }
            $sql = "INSERT INTO novedades (guia_novedad, cliente_novedad, estado_novedad, novedad, tracking, fecha, id_plataforma) VALUES ('$guia', '$nombre', '$codigo', '$nombre', '$tracking', '" . $novedad["fechaNovedad"] . "', '$id_plataforma')";
            $response = mysqli_query($this->market, $sql);
            $response = mysqli_fetch_assoc($response);
            print_r($response);
            if ($avisar) {
                //$this->enviarCorreo($guia);
            }
        } else {
            echo "No hay novedades";
        }
    }

    public function enviarCorreo($guia)
    {
        /* $datos = "SELECT * FROM facturas_cot WHERE numero_guia = '$guia' ";
        $select = $this->select($datos);
        $data_factura = $select[0];
        $id_plataforma = $data_factura['id_plataforma'];
        $id_usuario = $data_factura['id_usuario'];

        $datos = "SELECT * FROM users WHERE id_users = '$id_usuario' ";
        $select = $this->select($datos);
        $data_usuario = $select[0];
        $correo = $data_usuario['email_users'];

        require_once 'PHPMailer/Mail_devolucion.php';
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->SMTPDebug = $smtp_debug;
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->Port = 465;
        $mail->SMTPSecure = $smtp_secure;
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($smtp_from, $smtp_from_name);
        $mail->addAddress($correo);
        $mail->Subject = 'Novedad de pedido en Imporsuitpro';
        $mail->Body = $message_body_pedido;
        // $this->crearSubdominio($tienda);

        if ($mail->send()) {
            echo "Correo enviado";
        } else {
            //  echo "Error al enviar el correo: " . $mail->ErrorInfo;
        } */
    }
    public function masivo()
    {
        $sql = "SELECT * FROM facturas_cot WHERE numero_guia like 'IMP%';";
        $guias = $this->select($sql);
        foreach ($guias as $guia) {
            $this->verificar($guia['numero_guia']);
        }
    }

    public function verificar($guia)
    {
        $ch = curl_init("https://api.laarcourier.com:9727/guias/" . $guia);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        $url = "https://new.imporsuitpro.com/gestion/laar";
        $ch2 = curl_init($url);
        curl_setopt($ch2, CURLOPT_POST, 1);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, $response);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $response2 = curl_exec($ch2);
        curl_close($ch2);
        curl_close($ch);
        echo $response2;
    }
}
