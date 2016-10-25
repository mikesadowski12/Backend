<?php
namespace WellCat\Controllers;

use PDO;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use WellCat\JsonResponse;

class AddressController
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function Countries()
    {
        $sql ='SELECT countryid AS id, countryname AS name FROM country';

        $stmt= $this->app['db']->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if($result){
            $body = array( 'countries' => $result);
            $body['success'] = true;
            return new JsonResponse($body, 200);
        }
        else{
                $body = array( 
                        'success' => false,
                        'error' => 'no results for that id'   
                );

                return new JsonResponse($body, 404);
            }

    }

    public function Locations(Request $request, $country)
    {

        $sql ='SELECT locationid AS id, locationname AS name FROM location WHERE countryid = :countryID';

        $stmt= $this->app['db']->prepare($sql);
        $stmt->execute(array( ':countryID' => $country));

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if($result){
                $body = array( 'locations' => $result);
                $body['success'] = true;
                return new JsonResponse($body, 200);
        }
        else{
            $body = array( 
                'success' => false,
                'error' => 'no results for that id' 
                );

                return new JsonResponse($body, 404);
        }
    }
}