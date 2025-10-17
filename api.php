<?php
// --- CONFIGURATION ---
$dataDir = __DIR__; 
$dataFile = $dataDir . '/data.json';
$pinFile = $dataDir . '/pin.hash';

// --- GESTION DES REQUÊTES ---

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Action non spécifiée.']);
    exit;
}

$action = $input['action'];

// --- ROUTAGE DES ACTIONS ---

switch ($action) {
    case 'status':
        handleStatus();
        break;
    case 'set_pin':
        handleSetPin($input);
        break;
    case 'login':
        handleLogin($input);
        break;
    case 'get_data':
        handleGetData($input);
        break;
    case 'save_data':
        handleSaveData($input);
        break;
    case 'update_spot_history':
        handleUpdateSpotHistory($input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
        break;
}

// --- FONCTIONS DE GESTION ---

function handleStatus() {
    global $pinFile, $dataDir;
    echo json_encode([
        'success' => true, 
        'pinSet' => file_exists($pinFile),
        'writable' => is_writable($dataDir)
    ]);
}

function handleSetPin($input) {
    global $pinFile;
    if (file_exists($pinFile)) {
        echo json_encode(['success' => false, 'error' => 'Un PIN est déjà configuré.']);
        exit;
    }
    if (!is_writable(dirname($pinFile))) {
        echo json_encode(['success' => false, 'error' => 'Erreur de permission serveur.']);
        exit;
    }
    if (isset($input['pin']) && strlen($input['pin']) === 4) {
        $pinHash = password_hash($input['pin'], PASSWORD_DEFAULT);
        if (file_put_contents($pinFile, $pinHash) === false) {
             echo json_encode(['success' => false, 'error' => 'Impossible d\'écrire le fichier PIN.']);
             exit;
        }
        chmod($pinFile, 0664);
        echo json_encode(['success' => true, 'pinHash' => $pinHash]);
    } else {
        echo json_encode(['success' => false, 'error' => 'PIN invalide.']);
    }
}

function handleLogin($input) {
    global $pinFile;
    if (!file_exists($pinFile)) {
        echo json_encode(['success' => false, 'error' => 'Aucun PIN n\'est configuré.']);
        exit;
    }
    if (isset($input['pin'])) {
        $storedHash = file_get_contents($pinFile);
        if (password_verify($input['pin'], $storedHash)) {
            echo json_encode(['success' => true, 'pinHash' => $storedHash]);
        } else {
            echo json_encode(['success' => false, 'error' => 'PIN incorrect.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'PIN non fourni.']);
    }
}

function isPinValid($sessionHash) {
    global $pinFile;
    if (!file_exists($pinFile) || empty($sessionHash)) {
        return false;
    }
    $storedHash = file_get_contents($pinFile);
    return hash_equals($storedHash, $sessionHash);
}

/**
 * Lit le fichier de données de manière sécurisée avec un verrou partagé.
 * Empêche la lecture pendant qu'une écriture est en cours.
 */
function readDataFile() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ['assets' => [], 'coins' => [], 'priceHistory' => []];
    }
    
    $fp = fopen($dataFile, 'r');
    if (!$fp) {
        return ['assets' => [], 'coins' => [], 'priceHistory' => []];
    }

    flock($fp, LOCK_SH); // Verrou partagé (lecture)
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN); // Libération du verrou
    fclose($fp);
    
    return json_decode($content, true) ?: ['assets' => [], 'coins' => [], 'priceHistory' => []];
}

/**
 * Écrit dans le fichier de données de manière atomique et sécurisée.
 * Utilise un fichier temporaire et un verrou exclusif pour éviter la corruption.
 */
function writeDataFile($data) {
    global $dataFile;
    $tmpFile = $dataFile . '.tmp';
    
    $fp = fopen($tmpFile, 'w');
    if (!$fp) {
        throw new Exception("Impossible de créer le fichier temporaire.");
    }
    
    flock($fp, LOCK_EX); // Verrou exclusif (écriture)
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN); // Libération du verrou
    fclose($fp);

    chmod($tmpFile, 0664);

    // L'opération de renommage est atomique
    if (!rename($tmpFile, $dataFile)) {
        unlink($tmpFile);
        throw new Exception("Impossible de remplacer le fichier de données.");
    }
}

function handleGetData($input) {
    if (isset($input['pinHash']) && isPinValid($input['pinHash'])) {
        try {
            $data = readDataFile();
            echo json_encode($data);
        } catch (Exception $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Authentification échouée.']);
    }
}

function handleSaveData($input) {
    if (isset($input['pinHash']) && isPinValid($input['pinHash']) && isset($input['payload'])) {
        try {
            writeDataFile($input['payload']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Authentification ou données invalides.']);
    }
}

/**
 * Récupère les données d'un service externe de manière robuste avec cURL.
 * Inclut un timeout pour éviter de bloquer l'application.
 */
function fetchSpotFromExternal() {
    $url = "https://data-asg.goldprice.org/dbXRates/EUR";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout pour la connexion
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout total pour la requête

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        // Erreur cURL (ex: timeout, impossible de résoudre le nom de domaine)
        curl_close($ch);
        return null;
    }

    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode < 200 || $httpcode >= 300) {
        // Erreur HTTP (ex: 404, 500)
        return null;
    }

    return json_decode($response, true);
}

function handleUpdateSpotHistory($input) {
    if (!(isset($input['pinHash']) && isPinValid($input['pinHash']))) {
         echo json_encode(['success' => false, 'error' => 'Authentification échouée.']);
         exit;
    }

    try {
        $data = readDataFile();
        $today = date('Y-m-d');
        $lastEntryDate = null;

        if (!empty($data['priceHistory'])) {
            $lastEntry = end($data['priceHistory']);
            $lastEntryDate = $lastEntry['date'];
        }
        
        if ($lastEntryDate !== $today) {
            $spotData = fetchSpotFromExternal();
            if ($spotData && isset($spotData['items'][0])) {
                $rates = $spotData['items'][0];
                
                $dateExists = false;
                if(isset($data['priceHistory'])) {
                    foreach($data['priceHistory'] as $entry) {
                        if ($entry['date'] === $today) {
                            $dateExists = true;
                            break;
                        }
                    }
                }
                
                if (!$dateExists) {
                     $data['priceHistory'][] = [
                        'date' => $today,
                        'gold' => $rates['xauPrice'],
                        'silver' => $rates['xagPrice']
                    ];
                    usort($data['priceHistory'], function($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });
                    writeDataFile($data);
                    echo json_encode(['success' => true, 'updated' => true, 'message' => 'Historique des prix mis à jour.']);
                    exit;
                }
            } else {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['success' => false, 'updated' => false, 'message' => 'Impossible de récupérer les nouveaux cours.']);
                exit;
            }
        }
        
        echo json_encode(['success' => true, 'updated' => false, 'message' => 'Historique déjà à jour.']);

    } catch (Exception $e) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
