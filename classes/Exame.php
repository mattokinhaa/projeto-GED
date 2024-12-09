<?php
class Exame {
    private $db;
    private $upload;
    private $validator;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->upload = new Upload();
        $this->validator = new Validator();
    }

    public function create($data, $files) {
        try {
            $this->db->beginTransaction();

            if (!$this->validator->validarExame($data)) {
                throw new Exception(implode(", ", $this->validator->getErrors()));
            }

            // Upload do exame
            $arquivo_path = $this->upload->uploadFile($files['arquivo'], 'exames');
            if (!$arquivo_path) {
                throw new Exception("Erro ao fazer upload do exame");
            }

            $sql = "INSERT INTO exames_realizados ()"
            ?>