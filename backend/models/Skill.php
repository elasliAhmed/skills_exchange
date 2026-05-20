<?php
// Skill stub — skills table was removed; skills are stored directly as named strings on user_skills
class Skill {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function getAll() {
        return [];
    }

    public function findById($id) {
        return null;
    }

    public function create($name, $description = null) {
        return 0;
    }

    public function findOrCreate($name) {
        return 0;
    }
}