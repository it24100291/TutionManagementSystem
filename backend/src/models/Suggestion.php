<?php
class Suggestion {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->ensureReplyColumn();
    }
    

    public function create($userId, $type, $title, $description) {
        $stmt = $this->db->prepare("INSERT INTO suggestions_complaints (created_by, type, title, description, status) VALUES (?, ?, ?, ?, 'OPEN')");
        $stmt->execute([$userId, $type, $title, $description]);
        return $this->db->lastInsertId();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT s.*, u.full_name as creator_name FROM suggestions_complaints s JOIN users u ON s.created_by = u.id WHERE s.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getByUser($userId) {
        $stmt = $this->db->prepare("SELECT * FROM suggestions_complaints WHERE created_by = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getAllPaginated($page, $limit, $status = null, $type = null) {
        $offset = ($page - 1) * $limit;
        $params = [];
        $where = [];
        
        if ($status) {
            $where[] = "s.status = ?";
            $params[] = $status;
        }
        
        if ($type) {
            $where[] = "s.type = ?";
            $params[] = $type;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM suggestions_complaints s $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get paginated results
        $sql = "SELECT s.*, u.full_name as creator_name FROM suggestions_complaints s JOIN users u ON s.created_by = u.id $whereClause ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    

    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach (['status', 'admin_note', 'reply_message'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) return;
        
        $values[] = $id;
        $sql = "UPDATE suggestions_complaints SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    private function ensureReplyColumn() {
        $stmt = $this->db->query("SHOW COLUMNS FROM suggestions_complaints LIKE 'reply_message'");
        if (!$stmt->fetch()) {
            $this->db->exec("ALTER TABLE suggestions_complaints ADD COLUMN reply_message TEXT NULL AFTER admin_note");
        }
    }
}
