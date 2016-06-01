<?php

namespace Arcium\ApiBundle\Controller;

use Arcium\ApiBundle\Services\GameService;
use Arcium\ApiBundle\Services\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GameController extends Controller
{
    /** @var GameService $gameService */
    protected $gameService = null;

    /** @var UserService $userService */
    protected $userService = null;

    /**
     * @return GameService
     */
    protected function getGameService()
    {
        if( ! $this->gameService)
        {
            $this->gameService = $this->get('arcium_api.game_service');
        }

        return $this->gameService;
    }

    /**
     * @return UserService
     */
    protected function getUserService()
    {
        if( ! $this->userService)
        {
            $this->userService = $this->get('arcium_api.user_service');
        }

        return $this->userService;
    }

    /**
     * @param Request $request
     * @param $key
     * @param bool $required
     * @return mixed
     * @throws \Exception
     */
    protected function getRequestParameter(Request $request, $key, $required = true)
    {
        $value = $request->get($key);

        if( ! $value && $required)
        {
            throw new \Exception('Required parameter: '  . $key);
        }

        return $value;
    }


    /**
     * @Route("/api/user/login")
     * @Method("POST")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function LoginAction(Request $request)
    {
        $username = $this->getRequestParameter($request, 'username');
        $password = $this->getRequestParameter($request, 'password');

        $userToken = $this->getUserService()->loginUser($username, $password);

        // login failed
        if($userToken === false)
        {
            return new JsonResponse(['message' => 'Username and password combination were incorrect']);
        }

        return new JsonResponse(['user_token' => $userToken]);
    }

    /**
     * @Route("/api/user/signup")
     * @Method("POST")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function SignupAction(Request $request)
    {
        $username = $this->getRequestParameter($request, 'username');
        $password = $this->getRequestParameter($request, 'password');

        try
        {
            $this->getUserService()->signupUser($username, $password);
        }
        #TODO: handle exception types
        catch(\Exception $e)
        {
            return new JsonResponse(['reason' => $e->getMessage(), 'code' => $e->getCode()]);
        }

        // return an empty 201
        return new JsonResponse(null, 201);
    }

    /**
     * @Route("/api/game/create")
     * @Method("POST")
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function CreateGameAction(Request $request)
    {
        $userToken = $this->getRequestParameter($request, 'user_token');

        try
        {
            if( ! $this->getUserService()->validateUser($userToken))
            {
                throw new \Exception('User token ' . $userToken . ' is not valid!');
            }

            $gameToken = $this->getGameService()->createGame($userToken);
            $topCard = $this->getGameService()->retrieveTopCard($userToken, $gameToken);
        }
        #TODO: handle exception types
        catch(\Exception $e)
        {
            return new JsonResponse(['reason' => $e->getMessage(), 'code' => $e->getCode()]);
        }

        return new JsonResponse(['game_token' => $gameToken, 'top_card' => $topCard]);
    }

    /**
     * @Route("/api/game/submitAction")
     * @Method("POST")
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function SubmitActionAction(Request $request)
    {
        $userToken = $this->getRequestParameter($request, 'user_token');
        $gameToken = $this->getRequestParameter($request, 'game_token');
        $action = $this->getRequestParameter($request, 'action');

        try
        {
            $outcome = $this->getGameService()->submitAction($userToken, $gameToken, $action) ? 'correct' : 'incorrect';
            $topCard = $this->getGameService()->retrieveTopCard($userToken, $gameToken);
            $score = $this->getGameService()->retrieveScore($userToken, $gameToken);
        }
        #TODO: handle exception types
        catch(\Exception $e)
        {
            return new JsonResponse(['message' => $e->getMessage(), 'code' => $e->getCode()], 500);
        }

        return new JsonResponse(['outcome' => $outcome, 'top_card' => $topCard, 'score' => $score]);
    }
}
