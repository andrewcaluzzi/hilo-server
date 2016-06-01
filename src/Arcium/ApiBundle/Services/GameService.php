<?php

namespace Arcium\ApiBundle\Services;

use Arcium\ApiBundle\Entity\Game;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\MonologBundle\MonologBundle;

class GameService
{
    protected static $availableActions = ['higher', 'lower'];
    protected static $standardDeck = [
        'Ah' =>  52, 'Ad'  => 51, 'Ac'  => 50, 'As'  => 49,
        'Kh' =>  48, 'Kd'  => 47, 'Kc'  => 46, 'Ks'  => 45,
        'Qh' =>  44, 'Qd'  => 43, 'Qc'  => 42, 'Qs'  => 41,
        'Jh' =>  40, 'Jd'  => 39, 'Jc'  => 38, 'Js'  => 37,
        '10h' => 36, '10d' => 35, '10c' => 34, '10s' => 32,
        '9h' =>  31, '9d'  => 30, '9c'  => 29, '9s'  => 28,
        '8h' =>  27, '8d'  => 26, '8c'  => 25, '8s'  => 24,
        '7h' =>  23, '7d'  => 22, '7c'  => 21, '7s'  => 20,
        '6h' =>  19, '6d'  => 18, '6c'  => 17, '6s'  => 16,
        '5h' =>  16, '5d'  => 15, '5c'  => 14, '5s'  => 13,
        '4h' =>  12, '4d'  => 11, '4c'  => 10, '4s'  =>  9,
        '3h' =>   8, '3d'  =>  7, '3c'  =>  6, '3s'  =>  5,
        '2h' =>   4, '2d'  =>  3, '2c'  =>  2, '2s'  =>  1,
    ];

    protected $entityManager;
    protected $gameRepository;

    /**
     * GameService constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->setEntityManager($entityManager);
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param EntityManager $entityManager
     * @return $this
     */
    protected function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;

        return $this;
    }

    /**
     * @return \Arcium\ApiBundle\Repository\GameRepository
     */
    protected function getGameRepository()
    {
        if( ! $this->gameRepository)
        {
            $this->gameRepository = $this->getEntityManager()->getRepository('ArciumApiBundle:Game');
        }

        return $this->gameRepository;
    }

    /**
     * Returns a full shuffled deck as a comma-separated array
     *
     * @return string
     */
    protected function generateShuffledDeck()
    {
        $deck = array_keys(self::$standardDeck);
        shuffle($deck);

        return implode(',', $deck);
    }

    /**
     * Generates a unique game token
     *
     * @return string
     */
    protected function generateGameToken()
    {
        $gameToken = "";

        while(true)
        {
            // 32 character random string
            $gameToken = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 32)), 0, 32);

            if( ! $this->getGameRepository()->findOneBy(['gameToken' => $gameToken]))
            {
                break;
            }
        }

        return $gameToken;
    }

    /**
     * Attempts to load a game with a given user and game token
     *
     * @param $userToken
     * @param $gameToken
     * @param bool $considerActive
     * @return Game
     * @throws \Exception
     */
    protected function retrieveGame($userToken, $gameToken, $considerActive = true)
    {
        $criteria = [
            'userToken' => $userToken,
            'gameToken' => $gameToken,
        ];

        if($considerActive)
        {
            $criteria['active'] = true;
        }

        $game = $this->getGameRepository()->findOneBy($criteria);

        if( ! $game)
        {
            $message = 'Game with user token ' . $userToken . ' and game token ' . $gameToken . ' does not exist!';

            if($considerActive)
            {
                $message .= ' (only checking active games)';
            }

            throw new \Exception($message);
        }

        return $game;
    }

    /**
     * Deactivates a given Game (with an optional entity manager flush)
     *
     * @param Game $game
     * @param bool $flush
     */
    protected function deactivateGame(Game $game, $flush = true)
    {
        $game->setActive(false);
        $this->getEntityManager()->persist($game);

        if($flush)
        {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Creates a new game for a given user token
     *
     * @param $userToken
     * @return string
     * @throws \Exception
     */
    public function createGame($userToken)
    {
        if( ! $userToken)
        {
            throw new \Exception('User token cannot be null');
        }

        /** @var Game[] $existingGames */
        $existingGames = $this->getGameRepository()->findBy(['userToken' => $userToken, 'active' => true]);

        // deactivate any existing games
        foreach($existingGames as $existingGame)
        {
            $existingGame->setActive(false);
            $this->getEntityManager()->persist($existingGame);
        }

        $newGame = new Game();
        $newGame->setUserToken($userToken);
        $newGame->setGameToken($this->generateGameToken());
        $newGame->setCards($this->generateShuffledDeck());
        $newGame->setActive(true);
        $newGame->setScore(0);
        $newGame->setCreatedAt(new \DateTime());
        $this->getEntityManager()->persist($newGame);
        $this->getEntityManager()->flush();

        return $newGame->getGameToken();
    }

    /**
     * Attempts an action (higher or lower) to guess whether the next card
     *
     * @param $userToken
     * @param $gameToken
     * @param $action
     * @return bool
     * @throws \Exception
     */
    public function submitAction($userToken, $gameToken, $action)
    {
        #TODO: check time

        if( ! in_array($action, self::$availableActions))
        {
            throw new \Exception('Action ' . $action . ' not supported. Available actions: ' . implode(', ', self::$availableActions));
        }

        $game = $this->retrieveGame($userToken, $gameToken);
        $cards = explode(',', $game->getCards());

        if(count($cards) < 2)
        {
            $this->deactivateGame($game);
            throw new \Exception('Game does not have enough cards left to compare');
        }

        $currentCardValue = self::$standardDeck[$cards[0]];
        $nextCardValue = self::$standardDeck[$cards[1]];
        $outcome = false;

        // the next card will be higher
        if($action == 'higher')
        {
            $outcome = $nextCardValue > $currentCardValue;
        }

        // the next card will be lower
        else if($action == 'lower')
        {
            $outcome = $nextCardValue < $currentCardValue;
        }

        // remove the top card
        array_shift($cards);
        $game->setCards(implode(',', $cards));

        // deactivate the game if the player guessed incorrectly
        if( ! $outcome)
        {
            $this->deactivateGame($game, false);
        }

        // increment the score
        else
        {
            $game->setScore($game->getScore() + 1);
        }

        $this->getEntityManager()->persist($game);
        $this->getEntityManager()->flush();

        return $outcome;
    }

    /**
     * Retrieves the top card (doesn't remove it)
     *
     * @param $userToken
     * @param $gameToken
     * @return string
     */
    public function retrieveTopCard($userToken, $gameToken)
    {
        $game = $this->retrieveGame($userToken, $gameToken, false);
        $cards = explode(',', $game->getCards());

        return empty($cards) ? '' : $cards[0];
    }

    /**
     * @param $userToken
     * @param $gameToken
     * @return int
     * @throws \Exception
     */
    public function retrieveScore($userToken, $gameToken)
    {
        $game = $this->retrieveGame($userToken, $gameToken, false);
        return $game->getScore();
    }
}
