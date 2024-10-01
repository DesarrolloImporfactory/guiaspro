<?php
class ImageUploader
{
    private $target_dir;

    public function __construct($target_dir = "public/img/")
    {
        $this->target_dir = $target_dir;

        // Verificar si el directorio existe, si no, crearlo
        if (!is_dir($this->target_dir)) {
            mkdir($this->target_dir, 0777, true); // Crea el directorio con permisos 0777 y recursivamente
        }
    }

    public function uploadImage($image, $allowedFileTypes = ["jpg", "png", "jpeg"], $maxSize = 50000000)
    {
        $response = [
            'status' => 500,
            'title' => 'Error',
            'message' => '',
            'data' => null
        ];

        $imageFileType = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));
        $unique_name = uniqid('', true) . '.' . $imageFileType;
        $target_file = $this->target_dir . $unique_name;

        // Verifica si el archivo ya existe

        while (file_exists($target_file)) {
            $unique_name = uniqid('', true) . '.' . $imageFileType;
            $target_file = $this->target_dir . $unique_name;
        }

        // Verificación si es una imagen
        $check = getimagesize($image["tmp_name"]);
        if ($check === false) {
            $response['message'] = 'El archivo no es una imagen';
            return $response;
        }

        // Verificación del tamaño de la imagen
        if ($image["size"] > $maxSize) {
            $response['message'] = 'El archivo es muy grande';
            return $response;
        }

        // Verificación del tipo de archivo
        if (!in_array($imageFileType, $allowedFileTypes)) {
            $response['message'] = 'Solo se permiten archivos: ' . implode(', ', $allowedFileTypes);
            return $response;
        }

        // Mover el archivo a la ubicación final
        if (move_uploaded_file($image["tmp_name"], $target_file)) {
            $response['status'] = 200;
            $response['title'] = 'Petición exitosa';
            $response['message'] = 'Imagen subida correctamente';
            $response['data'] = $target_file;
        } else {
            $response['message'] = 'Error al mover el archivo';
        }

        return $response;
    }
}
