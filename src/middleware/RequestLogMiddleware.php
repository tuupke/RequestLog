<?php namespace Eventix\RequestLog\Middleware;

use Closure;

use Elasticsearch\ClientBuilder;
use Uuid;
use OAuthUser;
use App;


class RequestLogMiddleware
{

    private $hosts;
    private $start;
    private $requestUuid;

    public function __construct($hosts)
    {
        $this->hosts = [$hosts];
        $this->requestUuid = (string)Uuid::generate();
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->start = microtime(true);
        return $next($request);
    }


    /**
     * Handle outgoing response
     * Processed after response is sent
     *
     * @param $request
     * @param $response
     */
    public function terminate($request, $response)
    {
        $this->deleteIndex('requests');
        $this->createIndex('requests');
        $client = $this->getClient();

        $data = [
            "time"     => date('Y-m-d\TH:i:sP'),
            "timestamp"=> time(),
            "duration" => (microtime(true) - $this->start) * 1000,
            "durationUnit" => 'ms',
            "request"  => $this->getGet($request),
            "response"  => $response->getContent(),
        ];

        if(OAuthUser::isValid()){
            $data['user'] = json_decode(OAuthUser::get()->toJson());
        }

        $params = [
            'index' => 'requests',
            'type' => 'request',
            'id' => $this->requestUuid,
            'body' => $data,
        ];

        if(env('REQUEST_LOG', false)){
            $r = $client->index($params); // Catch Fails?
        }
    }

    /**
     * Create an array containing the result of executing all "get" methods of an object which require no parameters
     * and store the results in an array. The key of each attribute is the method name without the "get" prefix
     *
     * @param $object
     * @return array
     */
    private function getGet($object)
    {
        // Get all methods of the object.
        $all = get_class_methods($object);

        // Reduce away all methods which do not start with "get" and do have required parameters.
        $all = array_reduce($all, function ($carry, $item) use ($object) {
            $bb = new \ReflectionMethod($object, $item);
            if (strpos($item, "get") === 0 && $bb->getNumberOfRequiredParameters() === 0) {
                $carry[] = $item;
            }

            return $carry;
        }, []);

        // Sort the final array to prettify the results.
        sort($all);

        // Execute the methods
        $toFill = [];
        foreach ($all as $a) {
            if (empty($a) || $a === 'get') {
                continue;
            }

            $val = $object->$a();

            // Attempt json decoding, since having objects in mongo is better than having just the string
            $attempt = gettype($val) === "string" ? json_decode($val) : false;
            $toFill[substr($a, 3)] = $attempt === false || is_null($attempt) ? $val : $attempt;
        }

        return $toFill;
    }

    /**
     * Sets up an Elasticsearch client
     *
     * @return \Elasticsearch\Client
     */
    public function getClient(){
        return ClientBuilder::create()->setHosts($this->hosts)->build();
    }

    /**
     * Create an index
     *
     * @param $name
     */
    public function createIndex($name){
        $client = $this->getClient();

        $params = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 2,
                    'number_of_replicas' => 0
                ]
            ]
        ];

        $response = $client->indices()->create($params);
//        print_r($response);
    }

    /**
     * Delete an index
     *
     * @param $name
     */
    public function deleteIndex($name){
        $client = $this->getClient();

        $params = [
            'index' => $name
        ];

        $response = $client->indices()->delete($params);
    }



    /**
     * Get the current Request UUID
     *
     * @return string
     */
    public function getRequestUuid(){
        return $this->requestUuid;
    }

}
