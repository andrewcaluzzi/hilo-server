<?php

namespace Arcium\ApiBundle\Services;

use Doctrine\ORM\EntityManager;

class UserService
{
    protected $entityManager;

    /**
     * UserService constructor.
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
     * @return \Arcium\ApiBundle\Repository\UserRepository
     */
    protected function getUserRepository()
    {
        return $this->getEntityManager()->getRepository('ArciumApiBundle:User');
    }

    /**
     * @param $userToken
     *
     * @return bool
     */
    public function validateUser($userToken)
    {
        if( ! $this->getUserRepository()->findOneBy(['userToken' => $userToken]))
        {
            return false;
        }

        return true;
    }

    /**
     * @param $username
     * @param $password
     *
     * @throws \Exception
     */
    public function signupUser($username, $password)
    {
    }

    /**
     * @param $username
     * @param $password
     *
     * @return string|bool
     */
    public function loginUser($username, $password)
    {
    }
}
