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
    case 'get_current_spot': // Nouvelle action pour le cours en direct
        handleGetCurrentSpot();
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

function readDataFile() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ['assets' => [], 'coins' => [], 'priceHistory' => []];
    }

    $fp = fopen($dataFile, 'r');
    if (!$fp) {
        return ['assets' => [], 'coins' => [], 'priceHistory' => []];
    }

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return json_decode($content, true) ?: ['assets' => [], 'coins' => [], 'priceHistory' => []];
}

function writeDataFile($data) {
    global $dataFile;
    $tmpFile = $dataFile . '.tmp';

    $fp = fopen($tmpFile, 'w');
    if (!$fp) {
        throw new Exception("Impossible de créer le fichier temporaire.");
    }

    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    chmod($tmpFile, 0664);

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
 * Récupère les cours depuis une API externe en essayant plusieurs méthodes.
 * @return array|null Les données décodées ou un tableau d'erreur.
 */
function fetchSpotFromExternal() {
    $url = "https://data-asg.goldprice.org/dbXRates/EUR";
    $error_details = [];

    // Méthode 1: cURL (préférée)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!curl_errno($ch) && $httpcode == 200) {
            curl_close($ch);
            return json_decode($response, true);
        }
        $error_details[] = "cURL: " . curl_error($ch) . " (Code HTTP: " . $httpcode . ")";
        curl_close($ch);
    }

    // Méthode 2: file_get_contents (secours)
    // Utile si cURL a des problèmes de certificats SSL dans le conteneur.
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
            'timeout' => 15,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response !== false) {
        return json_decode($response, true);
    }
    
    $last_error = error_get_last();
    $error_details[] = "file_get_contents: " . ($last_error['message'] ?? 'Échec sans message');

    return ['error' => "Échec de la récupération externe. Détails: " . implode(' | ', $error_details)];
}


function handleGetCurrentSpot() {
    $spotData = fetchSpotFromExternal();

    if (isset($spotData['error'])) {
        echo json_encode(['success' => false, 'error' => $spotData['error']]);
        exit;
    }
     if ($spotData && isset($spotData['items'][0]['xauPrice'], $spotData['items'][0]['xagPrice'])) {
        echo json_encode([
            'success' => true,
            'gold' => $spotData['items'][0]['xauPrice'],
            'silver' => $spotData['items'][0]['xagPrice']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Réponse invalide de l\'API externe.']);
    }
}


function handleUpdateSpotHistory($input) {
    if (!(isset($input['pinHash']) && isPinValid($input['pinHash']))) {
         echo json_encode(['success' => false, 'error' => 'Authentification échouée.']);
         exit;
    }

    try {
        $data = readDataFile();
        $today = date('Y-m-d');
        
        $dateExists = false;
        if (!empty($data['priceHistory'])) {
            foreach ($data['priceHistory'] as $entry) {
                if ($entry['date'] === $today) {
                    $dateExists = true;
                    break;
                }
            }
        }

        if (!$dateExists) {
            $spotData = fetchSpotFromExternal();
            
            if (isset($spotData['error'])) {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['success' => false, 'updated' => false, 'error' => $spotData['error']]);
                exit;
            }
            if ($spotData && isset($spotData['items'][0]['xauPrice'], $spotData['items'][0]['xagPrice'])) {
                $rates = $spotData['items'][0];
                
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
            } else {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['success' => false, 'updated' => false, 'error' => 'Réponse invalide ou incomplète de l\'API externe.']);
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