<?php

namespace WellCat\Controllers;

use WellCat\JsonResponse;

class AnimalController
{
    protected $app;

    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
        $this->app['session']->start();
    }

    public function GetAnimals()
    {
        // Get animals from database
        $sql = 'SELECT * FROM animal';

        $stmt = $this->app['db']->prepare($sql);
        $success = $stmt->execute();

        if ($success) {
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($result == false) {
                return JsonReponse::userError('Unable to retreive animals');
            }

            return new JsonResponse($result);
        } else {
            return JsonReponse::userError('Unable to retreive animals');
        }
    }

    public function GetBreedsByAnimalId($animalId)
    {
        if (!$animalId) {
            return JsonResponse::missingParam('animalId');
        }

        // Get animals from database
        $sql = 'SELECT * FROM breed WHERE animaltypeid = :animaltypeid';

        $stmt = $this->app['db']->prepare($sql);
        $success = $stmt->execute(array(
            ':animaltypeid' => $animalId
        ));

        if ($success) {
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($result == false) {
                return JsonReponse::userError('Unable to retreive breeds for specified animal type');
            }

            return new JsonResponse($result);
        } else {
            return JsonReponse::userError('Unable to retreive breeds for specified animal type');
        }
    }
}
