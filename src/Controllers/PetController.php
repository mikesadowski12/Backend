<?php

namespace WellCat\Controllers;

use Silex\Application;
use PDO;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use WellCat\JsonResponse;

class PetController
{
    protected $app;

    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
        $this->app['session']->start();
    }

    /**
     * Function is given some required information to create a new pet in the system
     * @param Request $request holds JSON data of pet infromation needed to create a pet
     *       =>[text] name holds the pets name
     *       =>[int] breed holds the breedif of the pet
     *       =>[int] gender holds the genderid of the pet
     *       =>[date] dateOfBirth holds the date a pet was born in the format (yyyy-mm-dd)
     *       =>[real] weight holds the weight of the pet
     *       =>[real] height holds the height of the pet
     *       =>[int] length holds the length of the pet
     */
    public function Create(Request $request)
    {
        // Get parameters
        $petName = $request->request->get('name');
        $breed = $request->request->get('breed');
        $gender = $request->request->get('gender');
        $dateOfBirth = $request->request->get('dateOfBirth');
        $weight = $request->request->get('weight');
        $height = $request->request->get('height');
        $length = $request->request->get('length');

        // Validate parameters
        if (!$petName) {
            return JsonResponse::missingParam('name');
        }
        elseif (!$breed) {
            return JsonResponse::missingParam('breed');
        }
        elseif (!$gender) {
            return JsonResponse::missingParam('gender');
        }
        elseif (!$dateOfBirth) {
            return JsonResponse::missingParam('dateOfBirth');
        }
        elseif (!$weight) {
            return JsonResponse::missingParam('weight');
        }
        elseif (!$height) {
            return JsonResponse::missingParam('height');
        }
        elseif (!$length) {
            return JsonResponse::missingParam('length');
        }
        elseif (!DateTime::createFromFormat('Y-m-d', $dateOfBirth)) {
            return JsonResponse::userError('Invalid date.');
        }
        elseif (!$this->app['api.animalservice']->CheckBreedExists($breed)) {
            return JsonResponse::userError('Invalid breed.');
        }

        // Add pet to database
        $sql = 'INSERT INTO pet (ownerid, name, breedId, gender, dateofbirth, weight, height, length)
            VALUES (:ownerId, :name, :breed, :gender, :dateOfBirth, :weight, :height, :length)';

        $stmt = $this->app['db']->prepare($sql);
        $success = $stmt->execute(array(
            ':ownerId' => $this->app['session']->get('user')['userId'],
            ':name' => $petName,
            ':breed' => $breed,
            ':gender' => $gender,
            ':dateOfBirth' => $dateOfBirth,
            ':weight' => $weight,
            ':height' => $height,
            ':length' => $length
        ));

        if ($success) {
            return new JsonResponse();
        } 
        else {          
            return JsonReponse::userError('Unable to register pet.');
        }
    }

    /**
     * Function adds sharing of one pet to another user in the system
     * @param Request $request holds JSON data of user and pet info in order to share a pet with someone else
     *       =>[text] email holds the email in format (*@*.*)
     *       =>[int] petID hold the petID of a pet in the system
     *       =>[enum] access holds either 'write' OR 'read' to distiguish what access someone is gaining
     */
    public function SetAccessibility(Request $request)
    {
        // Get parameters
        $email = $request->request->get('email');
        $petID = $request->request->get('petID');
        $access = $request->request->get('access');

        // Validate parameters
        if (!$email) {
            return JsonResponse::missingParam('email');
        }
        elseif (!$petID) {
            return JsonResponse::missingParam('petID');
        }
        elseif (!$access) {
            return JsonResponse::missingParam('access');
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return JsonResponse::userError('Invalid email');
        }
        elseif (!$this->app['api.dbtypes']->IsValidPetAccessibilityValue($access)) {
            return JsonResponse::userError('Invalid accessibility value');
        }

        // Get userID based on email
        $userID = $this->app['api.auth']->GetUserIDByEmail($email);

        if (!$userID) {
            return JsonResponse::userError('Email provided is not associated with an existing WellCat account');
        }

        // Check to see if user already has accessibility with pet
        $currentAccess = $this->GetPetAccessibility($userID, $petID);

        // If no accessibility found, insert
        if (!$currentAccess) {
            $sql = 'INSERT INTO accessibility (userid, petid, access) 
                VALUES (:userid, :petid, :access)';

            $stmt = $this->app['db']->prepare($sql);
            $success = $stmt->execute(array(
                ':userid' => $userID,
                ':petid' => $petID,
                ':access' => $access
            ));

            if ($success) {
                return new JsonResponse();
            } 
            else {          
                return JsonReponse::userError('Unable to set pet accessibility.');
            }
        }
        // else update if current accessibility is not the same as the one trying to be set
        elseif ($currentAccess != $access) {
            $sql = 'UPDATE accessibility
                    SET access = :access
                    WHERE userid = :userid
                        AND petid = :petid';

            $stmt = $this->app['db']->prepare($sql);
            $success = $stmt->execute(array(
                ':userid' => $userID,
                ':petid' => $petID,
                ':access' => $access
            ));

            if ($success) {
                return new JsonResponse();
            } 
            else {          
                return JsonReponse::userError('Unable to update pet accessibility.');
            }
        }
        // else just return success
        else {
            return new JsonResponse();
        }
    }

    /**
     * Function gets pet information based on a given petID but if the user doesn't have 
     * access they do not get any information about the pet
     * @param [int] $petID holds the petID of a pet to be accessed
     */
    public function GetPet($petID)
    {
        //gets user session
        $user = $this->app['session']->get('user');

        //checks if user owns the pet
        $sql = 'SELECT NULL FROM pet WHERE petid = :petID AND ownerid = :user';
        $stmt = $this->app['db']->prepare($sql);
        $stmt->execute(array(
            ':petID' => $petID,
            ':user' => $user['userId']
        ));

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        //if user doesn't own the pet checks to see if they have access to the 
        //pet and if not a error message is returned to the caller
        if (!$result) {
            $petAccess = $this->GetPetAccessibility($user['userId'], $petID);
            if (!$petAccess) {
                $body = array(
                    'success' => false,
                    'error' => 'You do not have access to this pet'
                );

                return new JsonResponse ($body, 403);
            }
        }

        //Gets all information of a given pet as long as the user had gained access to the 
        //pet by owning or shared.
        $sql = 'SELECT name, breed, gender, dateofbirth AS dateOfBirth, weight, height, length FROM pet WHERE petid = :petID';

        $stmt = $this->app['db']->prepare($sql);
        $stmt->execute(array(
            ':petID' => $petID
        ));

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $body = array(
            'success' => true,
            'pet' => $result
        );

        return new JsonResponse($body,200);

    }

    /**
     * Function gets a listing of all pets a user has access to and returns them
     */
    public function GetAllPets()
    {
        $user = $this->app['session']->get('user');

        //get list of all pets that the current user owns
        $sql = 'SELECT p.petid, p.name, p.gender, p.breed, a.firstname, a.lastname, p.lastupdated FROM pet p INNER JOIN account a ON p.ownerid = a.userid WHERE p.ownerid = :user';

        $stmt = $this->app['db']->prepare($sql);
        $stmt->execute(array(
            ':user' => $user['userId']
        ));

        $personal = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        //get list of all pets that current user has access to.
        $sql = 'SELECT p.petid, p.name, p.gender, p.breed, a.firstname, a.lastname, p.lastupdated FROM pet p INNER JOIN account a ON p.ownerid = a.userid WHERE p.petid IN (SELECT f.petid FROM accessibility f WHERE f.userid = :user)';

        $stmt = $this->app['db']->prepare($sql);
        $stmt->execute(array(
            ':user' => $user['userId']
        ));

        $shared = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($personal || $shared) {
            $body = array(
                'success' => true,
                'personal' => $personal,
                'shared' => $shared
            );
            return new JsonResponse($body, 200);
        }
        else {
            $body = array(
                'success' => true,
                'message' => 'No pets found'
            );
            return new JsonResponse($body, 404);
        }
    }

    /**
     * Function trys to select a current shared access if it exits it returns the access. Otherwise returns null
     * @param [int] $userID holds a userID of a user in the system
     * @param [int] $petID  holds a petID of a pet in the system
     */
    private function GetPetAccessibility($userID, $petID)
    {
        // TODO: validate parameters and throw exception if null
        // For now, this function is only being called in a state where parameters have already been validated
        
        $sql = 'SELECT access FROM accessibility WHERE userid = :userid AND petid = :petid';

        $stmt= $this->app['db']->prepare($sql);
        $stmt->execute(array( 
            ':userid' => $userID,
            ':petid' => $petID
        ));

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result) {
            return $result['access'];
        }
        else {
            return null;
        }
    }
}
