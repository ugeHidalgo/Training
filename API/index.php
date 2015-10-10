<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '../libs/Slim/Slim.php';
 
\Slim\Slim::registerAutoloader();
 
$app = new \Slim\Slim();
 
// User id from db - Global Variable
$user_id = NULL;

/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app){
            // check for required params
            verifyRequiredParams(array('name', 'email', 'password'));
            $response = array();
 
            // reading post params
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');
 
            // validating email address
            validateEmail($email);
            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password);
 
            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "Successfully registered";
                echoRespnse(201, $response);
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "An error occurred while registereing";
                echoRespnse(200, $response);
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
                echoRespnse(200, $response);
            }
        });
 
/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields){
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
 
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
 
    // setting response content type to json
    $app->contentType('application/json');
 
    echo json_encode($response);
}

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('email', 'password'));
 
            // reading post params
            $email = $app->request()->post('email');
            $password = $app->request()->post('password');
            $response = array();
 
            $db = new DbHandler();
            // check for correct email and password
            if ($db->checkLogin($email, $password)) {
                // get the user by email
                $user = $db->getUserByEmail($email);
 
                if ($user != NULL) {
                    $response["error"] = false;
                    $response['name'] = $user['name'];
                    $response['email'] = $user['email'];
                    $response['apiKey'] = $user['api_key'];
                    $response['createdAt'] = $user['created_at'];
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "An error occurred. Please try again";
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Login failed. Incorrect credentials';
            }
 
            echoRespnse(200, $response);
        });
        
 /**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
 
    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
 
        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user = $db->getUserId($api_key);
            if ($user != NULL)
                $user_id = $user["id"];
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Creating new sporType in db
 * method POST
 * params - name
 * url - /sportType/
 */
$app->post('/sportType', 'authenticate', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('name'));
 
            $response = array();
            $name = $app->request->post('name');
 
            global $user_id;
            $db = new DbHandler();
 
            // creating new task
            $id = $db->createSportType($name);
 
            if ($id != NULL) {
                $response["error"] = false;
                $response["message"] = "Sport type created successfully";
                $response["id"] = $id;
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create sport type. Please try again";
            }
            echoRespnse(201, $response);
        });
        
 /**
 * Listing all tasks of particual user
 * method GET
 * url /sporttypes          
 */
$app->get('/sportTypes', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();
 
            // fetching all user tasks
            $result = $db->getAllSportTypes();
 
            $response["error"] = false;
            $response["sportTypes"] = array();
 
            // looping through result and preparing tasks array
            while ($sportType = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $sportType["id"];
                $tmp["name"] = $sportType["name"];
                array_push($response["sportTypes"], $tmp);
            }
 
            echoRespnse(200, $response);
        });
        
 /**
 * Listing single task of particual user
 * method GET
 * url /tasks/:id
 * Will return 404 if the task doesn't belongs to user
 */
$app->get('/sportType/:id', 'authenticate', function($id) {
            $response = array();
            $db = new DbHandler();
 
            // fetch task
            $result = $db->getSportType($id);
 
            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["name"] = $result["name"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Sport type with id ". $id . " was NOT found.";
                echoRespnse(404, $response);
            }
        });
        
 /**
 * Updating existing task
 * method PUT
 * params task, status
 * url - /tasks/:id
 */
$app->put('/sportType/:id', 'authenticate', function($id) use($app) {
            // check for required params
            verifyRequiredParams(array('name'));
            
            $name = $app->request->put('name');
 
            $db = new DbHandler();
            $response = array();
 
            // updating task
            $result = $db->updateSportType($id, $name);
            if ($result) {
                // task updated successfully
                $response["error"] = false;
                $response["message"] = "Sport type with id: ". $id ." updated successfully";
            } else {
                // task failed to update
                $response["error"] = true;
                $response["message"] = "Sport type with id: ". $id ."was NOT updated because it was not found. Please try again!";
            }
            echoRespnse(200, $response);
        });
        
/**
 * Deleting task. Users can delete only their tasks
 * method DELETE
 * url /tasks
 */
$app->delete('/sportType/:id', 'authenticate', function($id) use($app) {
 
            $db = new DbHandler();
            $response = array();
            $result = $db->deleteSportType($id);
            if ($result) {
                // task deleted successfully
                $response["error"] = false;
                $response["message"] = "Sport type with id: ". $id ." deleted successfully";
            } else {
                // task failed to delete
                $response["error"] = true;
                $response["message"] = "Sport type with id: ". $id ." was NOT deleted because it was not found. Please try again!";
            }
            echoRespnse(200, $response);
        });
        

 
$app->run();
?>
