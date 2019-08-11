<?

  namespace Daterange;

  use Daterange\v1\Request\Request as Request;
  use Daterange\v1\Service\RoutingService as RoutingService;

  // Classes autoloader.
  require_once 'Config/Autoloader.inc.php';

  // Sets headers.
//  header("Access-Control-Allow-Origin: *");
//  header("Content-Type: " . CONTENT_TYPE_CHARSET_JSON);
//  header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
//  header("Access-Control-Allow-Headers: Content-Type");

  // Starts a new request.
  $request = new Request();

  // Starts an instance of the routing service.
  $router = new RoutingService($request, MYSQL);

  // Process the request.
  $router->process_request();

  // Returns the response.
  $router->encode_response();

  exit();
