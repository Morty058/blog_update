<?php
include_once(ROOT_PATH . "/app/database/db.php");
include_once(ROOT_PATH . "/app/helpers/middleware.php");

use Laminas\Http\Client;
use Laminas\Http\Request;

$table = 'users';

// Pobranie wszystkich użytkowników przez API
$clientUsers = new Client('http://localhost:8080/users', ['timeout' => 30]);
$clientUsers->setMethod(Request::METHOD_GET);
$responseUsers = $clientUsers->send();
if ($responseUsers->isSuccess()) {
    $all_users = json_decode($responseUsers->getBody(), true);
} else {
    $all_users = [];
}

$admins = array();
$regular_users = array();
foreach ($all_users as $user) {
    if ($user['admin'] == 1) {
        $admins[] = $user;
    } else {
        $regular_users[] = $user;
    }
}

$errors = array();
$id = '';
$username = '';
$admin = '';
$email = '';
$password = '';
$passwordConf = '';

function loginUser($user) {
    $_SESSION['id'] = $user['id'];      
    $_SESSION['username'] = $user['username'];  
    $_SESSION['admin'] = $user['admin'];  
    $_SESSION['message'] = 'Zostałeś poprawnie zalogowany';  
    $_SESSION['type'] = 'success';

    if ($_SESSION['admin']) {
        header('location:' . BASE_URL . '/admin/dashboard.php');
    } else {
        header('location:' . BASE_URL . '/index.php'); 
    }
    exit();
}

// Rejestracja użytkownika / tworzenie administratora
if (isset($_POST['register-btn']) || isset($_POST['create-admin'])) {

    unset($_POST['register-btn'], $_POST['passwordConf'], $_POST['create-admin']);
    $_POST['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Ustawienie roli
    $_POST['admin'] = isset($_POST['admin']) ? 1 : 0;
        
    // Wysyłanie żądania POST do API w celu utworzenia użytkownika
    $client = new Laminas\Http\Client('http://localhost:8080/users', ['timeout' => 30]);
    $client->setMethod(Laminas\Http\Request::METHOD_POST);
    // Ustaw nagłówki, aby API wiedziało, że wysyłamy i oczekujemy JSON
    $client->setHeaders([
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ]);
    $client->setEncType('application/json');
    $client->setRawBody(json_encode($_POST));
    $response = $client->send();
        
    if ($response->isSuccess()) {
        // Zakładamy, że API zwraca tylko ID nowego użytkownika, np. { "id": 123 }
        $result = json_decode($response->getBody(), true);
        if (isset($result['id'])) {
            // Wykonaj dodatkowe żądanie GET, aby pobrać pełne dane użytkownika
            $clientGet = new Laminas\Http\Client('http://localhost:8080/users/' . $result['id'], ['timeout' => 30]);
            $clientGet->setMethod(Laminas\Http\Request::METHOD_GET);
            $clientGet->setHeaders(['Accept' => 'application/json']);
            $responseGet = $clientGet->send();
            if ($responseGet->isSuccess()) {
                $user = json_decode($responseGet->getBody(), true);
            } else {
                $_SESSION['message'] = "Błąd podczas pobierania danych użytkownika: " . $responseGet->getStatusCode();
                exit();
            }
        } else {
            // Jeśli API zwróciło pełny obiekt użytkownika, użyj go bez dodatkowego żądania GET
            $user = json_decode($response->getBody(), true);
        }
        
        if ($_POST['admin'] == 1) {
            $_SESSION['message'] = "Administrator został utworzony";
            $_SESSION['type'] = "success";
            header('location: ' . BASE_URL . '/admin/users/index.php');
            exit();
        } else {
            loginUser($user);
        }
    } else {
        $_SESSION['message'] = "Błąd podczas rejestracji: " . $response->getStatusCode();
    }
}

// Aktualizacja użytkownika
if (isset($_POST['update-user'])) {
    adminOnly();
    $errors = validateUser($_POST);

    if (count($errors) === 0) {
        $id = $_POST['id'];
        unset($_POST['passwordConf'], $_POST['update-user'], $_POST['id']);
        $_POST['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $_POST['admin'] = isset($_POST['admin']) ? 1 : 0;
        
        // Wysyłanie żądania PUT do API w celu aktualizacji użytkownika
        $client = new Client('http://localhost:8080/users/' . $id, ['timeout' => 30]);
        $client->setMethod(Request::METHOD_PUT);
        $client->setEncType('application/json');
        $client->setRawBody(json_encode($_POST));
        $response = $client->send();
        
        if ($response->isSuccess()) {
            $_SESSION['message'] = "Dane użytkownika zostały zaktualizowane";
            $_SESSION['type'] = "success";
            header('location: ' . BASE_URL . '/admin/users/index.php');
            exit();
        } else {
            $_SESSION['message'] = "Błąd podczas aktualizacji użytkownika: " . $response->getStatusCode();
        }
    } else {
        $username = $_POST['username'];
        $admin = isset($_POST['admin']) ? 1 : 0;
        $email = $_POST['email'];
        $password = $_POST['password'];
        $passwordConf = $_POST['passwordConf'];
    }
}

// Pobieranie pojedynczego użytkownika przez API
if (isset($_GET['id'])) {
    $userId = $_GET['id'];
    $client = new Client('http://localhost:8080/users/' . $userId, ['timeout' => 30]);
    $client->setMethod(Request::METHOD_GET);
    $response = $client->send();
    
    if ($response->isSuccess()) {
        $user = json_decode($response->getBody(), true);
        $id = $user['id'];
        $username = $user['username'];
        $email = $user['email'];
        $admin = $user['admin']; 
    } else {
        $_SESSION['message'] = "Nie udało się pobrać użytkownika: " . $response->getStatusCode();
        $id = $username = $email = $admin = '';
    }
}

// Logowanie użytkownika
if (isset($_POST['login-btn'])) {
    // Pobierz wszystkich użytkowników
    $client = new Client('http://localhost:8080/users', ['timeout' => 30]);
    $client->setMethod(Request::METHOD_GET);
    $client->setHeaders(['Accept' => 'application/json']);
    $response = $client->send();
    
    if ($response->isSuccess()) {
        $data = json_decode($response->getBody(), true);
        // Pobieramy tablicę użytkowników z odpowiedzi
        $users = isset($data['_embedded']['users']) ? $data['_embedded']['users'] : [];
       // var_dump($users); // Dla debugowania – usuń po sprawdzeniu
        $user = null;
        
        // Szukamy użytkownika o podanym username
        foreach ($users as $u) {
            if (isset($u['username']) && trim($u['username']) === trim($_POST['username'])) {
                $user = $u;
                break;
            }
        }
        
        if ($user && password_verify($_POST['password'], $user['password'])) {
            loginUser($user);
        } else {
            array_push($errors, 'Błędna Nazwa Użytkownika lub Hasło');
        }
    } else {
        array_push($errors, 'Błąd logowania: ' . $response->getStatusCode());
    }
}

// Usuwanie użytkownika przez API
if (isset($_GET['delete_id'])) {
    adminOnly();
    $userId = $_GET['delete_id'];
    $client = new Client('http://localhost:8080/users/' . $userId, ['timeout' => 30]);
    $client->setMethod(Request::METHOD_DELETE);
    $response = $client->send();
    
    if ($response->isSuccess()) {
        $_SESSION['message'] = "Użytkownik został usunięty";
        $_SESSION['type'] = "success";
        header('location: ' . BASE_URL . '/admin/users/index.php');
        exit();
    } else {
        $_SESSION['message'] = "Błąd przy usuwaniu użytkownika: " . $response->getStatusCode();
    }

}