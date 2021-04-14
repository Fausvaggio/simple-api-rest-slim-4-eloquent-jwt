<?php
require 'vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;

use \Firebase\JWT\JWT;

use Slim\Factory\AppFactory;

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");         
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers:{$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}


$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->setBasePath("/ApiRestSLIM");
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$container=$app->getContainer();

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'database',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);
$capsule->setEventDispatcher(new Dispatcher(new Container));
$capsule->setAsGlobal();
$capsule->bootEloquent();

#region Messages

$MessagesMaster = [
    'SuccessRegister'   => 'Descripción registrada.',
    'SuccessUpdate'     => 'Descripción actualizada.',
    'SuccessDelete'     => 'Descripción eliminada.',    
    'WarningRegister'   => 'La descripción ya han sido registrada.',
    'WarningUpdate'     => 'No se puede actualizar, la descripción ya ha sido registrada.',
    'WarningDelete'     => 'No se puede eliminar la descripción.',
    'ErrorRegister'     => '¡Error! En el registro de la descripción.',
    'ErrorUpdate'       => '¡Error! No se puede actualizar la descripción.',
    'ErrorDelete'       => '¡Error! No se puede eliminar la descripción.',
    'UpdateStatus'      => 'Estado ha sido actualizado.',    
    'ErrorStatus'       => 'No se pudo actualizar el estado.',
    'Danger'            => 'Ocurrió un error en el proceso.',
    'Empty'             => 'No hay data registrada.',
    'Info'              => 'Listado de datos.',
];

#endregion

function generateToken($data){
    $now = time();
    $future = strtotime('+1 minute',$now);
    $secret = "mysecret";

    $payload = [
        "jti"=>$data,
        "iat"=>$now,
        "exp"=>$future
    ];

    return JWT::encode($payload,$secret,"HS256");
}

#region Master
$app->get('/GetMasters/{master}/{status}', function (Request $request, Response $response, $args) use ($MessagesMaster) {    
    try
    {                    
        if($args['status']=="All"){
            $data = Capsule::table('tb'.$args['master'])->get();
        }    
        else{
            $data = Capsule::table('tb'.$args['master'])->where('Status', $args['status'])->get();
        }        
                                  
        if($data->count()>0){
            $payload =  json_encode(array("status"=>true, "type"=>"info", "message"=>$MessagesMaster['Info'], "data"=>$data), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        else{
            $payload = json_encode(array("status"=>false,"type"=>"empty","message"=>$MessagesMaster['Empty']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
    catch (Exception $error)
    {        
        $payload = json_encode(array("status"=>false,"type"=>"danger", "message"=>$error->errorInfo[2]), JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');      
    } 
});

$app->get('/GetMaster/{master}/{id}', function (Request $request, Response $response, $args) use ($MessagesMaster) {
    try
    {                    
        $data = Capsule::table('tb'.$args['master'])->where('Id', $args['id'])->get();        
        if($data->count()>0){
            $payload =  json_encode(array("status"=>true, "type"=>"info", "message"=>$MessagesMaster['Info'], "data"=>$data), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        else{
            $payload = json_encode(array("status"=>false,"type"=>"empty","message"=>$MessagesMaster['Empty']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
    catch (Exception $error)
    {        
        $payload = json_encode(array("status"=>false,"type"=>"danger", "message"=>$error->errorInfo[2]), JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');      
    } 
});

$app->post('/SetMasters', function (Request $request, Response $response, $args) use ($MessagesMaster): Response {        
    try
    {            
        $args = $request->getParsedBody();
        $values = [            
            'Description'   => $args['description'],
            'Status'        => '1',
        ];
    
        $id=Capsule::table('tb'.$args['master'])->insertGetId($values);
        if($id !== 0){                                  
            $payload =  json_encode(array("status" => true, "type"=>"success", "message" => $MessagesMaster['SuccessRegister']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        else{
            $payload = json_encode(array("status"=>false, "type"=>"error", "message" => $MessagesMaster['ErrorRegister']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
    catch (Exception $error)
    {   
        //    1062 = Duplicado
        //    1146 = Tabla no existe
        //    1054 = Campo no existe
        
        if($error->errorInfo[1]==1062){
            $payload = json_encode(array("status"=>false, "type"=>"warning", "message"=>$MessagesMaster['WarningRegister']), JSON_PRETTY_PRINT);    
        } 
        else{
            $payload = json_encode(array("status"=>false, "type"=>"warning", "message"=>$error->errorInfo[2],"code"=>$error->errorInfo[1]), JSON_PRETTY_PRINT);
        }    
        
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');      
    }  
});

$app->put('/UpdMasters', function (Request $request, Response $response, $args) use ($MessagesMaster): Response {     
    try
    {            
        $args = $request->getParsedBody();
        $dataId = (int)$args['Id'];
        $values = [
            'Description'   => $args['description']
        ];
    
        $affected=Capsule::table('tb'.$args['master'])->where(['Id' => $dataId])->update($values);        
        if($affected > 0){            
            $payload =  json_encode(array("status" => true, "type"=>"success", "message" => $MessagesMaster['SuccessUpdate']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        else{
            $payload = json_encode(array("status"=>false, "type"=>"error", "message" => $MessagesMaster['ErrorUpdate']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
    catch (Exception $error)
    {   
            //1062 = Duplicado
            //1146 = Tabla no existe
            //1054 = Campo no existe
        
        if($error->errorInfo[1]==1062){
            $payload = json_encode(array("status"=>false, "type"=>"warning", "message"=>$MessagesMaster['WarningUpdate']), JSON_PRETTY_PRINT);    
        } 
        else{
            $payload = json_encode(array("status"=>false, "type"=>"warning", "message"=>$error->errorInfo[2],"code"=>$error->errorInfo[1]), JSON_PRETTY_PRINT);
        }    
        
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');      
    }  
    
});

$app->put('/UpdStatusMasters', function (Request $request, Response $response, $args) use ($MessagesMaster): Response { 
    try
    {            
        $args = $request->getParsedBody();
        $dataId = (int)$args['Id'];
        $values = [
            'Status'    => $args['Status']            
        ];
    
        $affected=Capsule::table('tb'.$args['master'])->where(['Id' => $dataId])->update($values);                
        if($affected > 0){            
            $payload =  json_encode(array("status" => true, "type"=>"success", "message" => $MessagesMaster['UpdateStatus']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
        else{
            $payload = json_encode(array("status"=>false, "type"=>"error", "message" => $MessagesMaster['ErrorStatus']), JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
    catch (Exception $error)
    {           
        $payload = json_encode(array("status"=>false, "type"=>"warning", "message"=>$error->errorInfo[2],"code"=>$error->errorInfo[1]), JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');      
    }  
    
});

$app->delete('/DelMasters/{id}/{master}', function ($request, $response, array $args) use ($MessagesMaster) {
    try
    {                    
        $affected=Capsule::table('tb'.$args['master'])->where(['Id' => $args['id']])->delete(); 
        $payload =  json_encode(array("status" => true, "type"=>"success", "message" => $MessagesMaster['SuccessDelete']), JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
    catch (Exception $error)
    {           
        $payload = json_encode(array("status"=>false, "type"=>"warning", "message"=>$error->errorInfo[2],"code"=>$error->errorInfo[1]), JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');      
    }  
});
#endregion

try {
    $app->run();     
} catch (Exception $e) {      
  die( json_encode(array("status" => "failed", "message" => "This action is not allowed"))); 
}
?>
