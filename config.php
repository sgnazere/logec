<?php
// Configuration des sessions (AVANT session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 86400); // 24 heures
    ini_set('session.gc_maxlifetime', 86400); // 24 heures
    session_start();
}

// Configuration de la base de données
define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eclog');

// Configuration générale
define('SITE_NAME', 'LOGEC - Gestion de Véhicules');
define('TIME_ZONE', 'Africa/Abidjan');
date_default_timezone_set(TIME_ZONE);

// Clé secrète pour la licence
define('LICENSE_SECRET_KEY', 'LogecVehicule2024!');

// Fonction de connexion à la base de données
function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                )
            );
        } catch(PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }
    
    return $db;
}

// Initialiser la connexion à la base de données
$db = getDBConnection();

// Fonctions utilitaires
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['is_logged']) && $_SESSION['is_logged'] === true;
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}

function redirect($page) {
    header("Location: $page");
    exit();
}

// Fonction pour nettoyer les entrées
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour formater les dates
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Fonction pour calculer la distance entre deux points GPS
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return $miles * 1.609344; // Convert to kilometers
}

// Fonction pour vérifier les conflits de planning
function checkScheduleConflict($startDate, $endDate, $vehicleId = null, $driverId = null) {
    global $db;
    
    $conditions = [];
    $params = [];
    
    if ($vehicleId) {
        $conditions[] = "vehicule_id = :vehicule_id";
        $params[':vehicule_id'] = $vehicleId;
    }
    
    if ($driverId) {
        $conditions[] = "chauffeur_id = :chauffeur_id";
        $params[':chauffeur_id'] = $driverId;
    }
    
    $conditions[] = "(
        (date_depart BETWEEN :start_date AND :end_date) OR
        (date_retour BETWEEN :start_date AND :end_date) OR
        (:start_date BETWEEN date_depart AND date_retour)
    )";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
    
    $whereClause = implode(" AND ", $conditions);
    
    $query = $db->prepare("
        SELECT COUNT(*) as conflict_count 
        FROM deplacements 
        WHERE $whereClause AND statut != 'annule'
    ");
    
    $query->execute($params);
    $result = $query->fetch();
    
    return $result['conflict_count'] > 0;
}

// Fonction pour regrouper les demandes similaires
function groupSimilarRequests($date, $maxDistance = 5) {
    global $db;
    
    $groups = [];
    
    // Récupérer toutes les demandes pour la date donnée
    $query = $db->prepare("
        SELECT 
            dv.*, 
            d.latitude, 
            d.longitude
        FROM demandes_vehicules dv
        JOIN destinations d ON dv.destination_id = d.id
        WHERE DATE(dv.date_depart) = DATE(:date)
        AND dv.statut = 'en_attente'
        ORDER BY dv.urgence DESC, dv.date_depart ASC
    ");
    
    $query->execute([':date' => $date]);
    $requests = $query->fetchAll();
    
    // Regrouper les demandes par proximité
    foreach ($requests as $request) {
        $grouped = false;
        
        foreach ($groups as &$group) {
            $distance = calculateDistance(
                $request['latitude'],
                $request['longitude'],
                $group[0]['latitude'],
                $group[0]['longitude']
            );
            
            if ($distance <= $maxDistance) {
                $group[] = $request;
                $grouped = true;
                break;
            }
        }
        
        if (!$grouped) {
            $groups[] = [$request];
        }
    }
    
    return $groups;
}
?> 