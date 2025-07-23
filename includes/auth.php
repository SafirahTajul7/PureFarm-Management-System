<?php
// auth.php

class Auth {
    private static $instance = null;
    private $user_id = null;
    private $role = null;
    private $authenticated = false;

    // Private constructor for singleton
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialize authentication state from session
        if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
            $this->user_id = $_SESSION['user_id'];
            $this->role = $_SESSION['role'];
            $this->authenticated = true;
        }
    }

    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Auth();
        }
        return self::$instance;
    }

    // Check if user is authenticated and has admin role
    public function checkAdmin() {
        if (!$this->authenticated || $this->role !== 'admin') {
            header("Location: login.php?error=unauthorized");
            exit();
        }
    }

    // Check if user is authenticated and has supervisor role
    public function checkSupervisor() {
        if (!$this->authenticated || $this->role !== 'supervisor') {
            header("Location: login.php?error=unauthorized");
            exit();
        }
    }
    
    // Check if user is admin or supervisor (for pages that both roles can access)
    public function checkAdminOrSupervisor() {
        if (!$this->authenticated || !in_array($this->role, ['admin', 'supervisor'])) {
            header("Location: login.php?error=unauthorized");
            exit();
        }
    }

    // Check if user has any valid role
    public function checkAuthenticated() {
        if (!$this->authenticated) {
            header("Location: login.php?error=authentication_required");
            exit();
        }
    }

    // Get current user's ID with debugging
    public function getUserId() {
        if (!$this->authenticated) {
            error_log("getUserId called but user is not authenticated");
            return null;
        }
        if ($this->user_id === null) {
            error_log("getUserId returned null despite authenticated=true");
        }
        return $this->user_id;
    }

    // Get current user's role
    public function getRole() {
        return $this->role;
    }

    // Check if user has specific role
    public function hasRole($role) {
        return $this->role === $role;
    }

    // Check if user has permission
    public function hasPermission() {
        return in_array($this->role, ['admin', 'supervisor']);
    }

    // Logout user
    public function logout() {
        session_unset();
        session_destroy();
        $this->user_id = null;
        $this->role = null;
        $this->authenticated = false;
        header("Location: login.php");
        exit();
    }

    public function user() {
        if (!$this->authenticated) {
            return null;
        }
        
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$this->user_id]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Error in Auth::user(): " . $e->getMessage());
            return null;
        }
    }
}

// Helper function for quick access
function auth() {
    return Auth::getInstance();
}

// Example usage in your files:
// require_once 'auth.php';
// auth()->checkAdmin();  // For admin-only pages
// auth()->checkSupervisor(); // For supervisor-only pages
// auth()->checkAuthenticated(); // For any authenticated user
?>