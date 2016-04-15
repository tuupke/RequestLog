<?php namespace PharmIT\RequestLog\Middleware;

use Closure;

use MongoDB\BSON\UTCDatetime;
use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;

class RequestLogMiddleware
{

    private $start;
    private $cURI;

    public function __construct($connectionURI)
    {
        $this->cURI = $connectionURI;
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

    public function terminate($request, $response)
    {
        $manager = new Manager("mongodb://localhost:27017");

        $bulk = new BulkWrite;

        $bulk->insert([
            "time"     => new UTCDatetime(microtime(true) * 1000),
            "duration" => (microtime(true) - $this->start) * 1000 . ' ms',
            "request"  => $this->getGet($request),
            "response" => $this->getGet($response),
        ]);

        $manager->executeBulkWrite('MedAPI.RequestResponse', $bulk);
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

}
