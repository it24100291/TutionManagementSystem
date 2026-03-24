<?php
class SuggestionController {
    public function create() {
        $userId = $_SESSION['user']['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $type = isset($data['type']) ? $data['type'] : '';
        $title = isset($data['title']) ? trim($data['title']) : '';
        $description = isset($data['description']) ? trim($data['description']) : '';
        
        // Validation
        if (!$type || !$title || !$description) {
            Response::error('Missing required fields');
        }
        
        if (!in_array($type, ['SUGGESTION', 'COMPLAINT'])) {
            Response::error('Invalid type. Must be SUGGESTION or COMPLAINT');
        }
        
        if (strlen($title) > 150) {
            Response::error('Title too long (max 150 characters)');
        }
        
        $suggestion = new Suggestion();
        $id = $suggestion->create($userId, $type, $title, $description);
        $created = $suggestion->getById($id);
        
        Response::success($created, 201);
    }
    
    public function getMine() {
        $userId = $_SESSION['user']['id'];
        $suggestion = new Suggestion();
        $items = $suggestion->getByUser($userId);
        
        Response::success($items);
    }
    
    public function getAll() {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null;
        
        $suggestion = new Suggestion();
        $result = $suggestion->getAllPaginated($page, $limit, $status, $type);
        
        Response::success($result);
    }
    
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $status = isset($data['status']) ? $data['status'] : null;
        $adminNote = isset($data['admin_note']) ? $data['admin_note'] : null;
        $replyMessage = isset($data['reply_message']) ? $data['reply_message'] : null;
        
        if ($status && !in_array($status, ['OPEN', 'IN_PROGRESS', 'RESOLVED'])) {
            Response::error('Invalid status. Must be OPEN, IN_PROGRESS, or RESOLVED');
        }
        
        $updateData = [];
        if ($status) $updateData['status'] = $status;
        if ($adminNote !== null) $updateData['admin_note'] = trim($adminNote);
        if ($replyMessage !== null) $updateData['reply_message'] = trim($replyMessage);
        
        if (empty($updateData)) {
            Response::error('No valid fields to update');
        }
        
        $suggestion = new Suggestion();
        $suggestion->update($id, $updateData);
        
        $updated = $suggestion->getById($id);
        if (!$updated) {
            Response::error('Suggestion not found', 404);
        }
        
        Response::success($updated);
    }
}
