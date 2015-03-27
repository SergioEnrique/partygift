<?php

/*
 * This file is part of the HWIOAuthBundle package.
 *
 * (c) Hardware.Info <opensource@hardware.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NW\OAuthBundle\Form;

use HWI\Bundle\OAuthBundle\Form\RegistrationFormHandlerInterface;
use FOS\UserBundle\Form\Handler\RegistrationFormHandler;
use FOS\UserBundle\Mailer\MailerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use FOS\UserBundle\Util\TokenGenerator;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * FOSUBRegistrationFormHandler
 *
 * @author Sergio Vargas <sergioenrique@me.com>
 */
class FOSUBRegistrationFormHandler implements RegistrationFormHandlerInterface
{
    /**
     * @var UserManagerInterface
     */
    protected $userManager;

    /**
     * @var MailerInterface
     */
    protected $mailer;

    /**
     * @var RegistrationFormHandler
     */
    protected $registrationFormHandler;

    /**
     * @var TokenGenerator
     */
    protected $tokenGenerator;

    /**
     * @var integer
     */
    protected $iterations;

    /**
     * Constructor.
     *
     * @param UserManagerInterface $userManager    FOSUB user manager
     * @param MailerInterface      $mailer         FOSUB mailer
     * @param TokenGenerator       $tokenGenerator FOSUB token generator
     * @param integer              $iterations     Amount of attempts that should be made to 'guess' a unique username
     */
    public function __construct(UserManagerInterface $userManager, MailerInterface $mailer, TokenGenerator $tokenGenerator = null, $iterations = 5)
    {
        $this->userManager = $userManager;
        $this->mailer = $mailer;
        $this->tokenGenerator = $tokenGenerator;
        $this->iterations = $iterations;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Request $request, Form $form, UserResponseInterface $userInformation)
    {
        if (null !== $this->registrationFormHandler) {
            $formHandler = $this->reconstructFormHandler($request, $form);

            // make FOSUB process the form already
            $processed = $formHandler->process();

            // if the form is not posted we'll try to set some properties
            if (!$request->isMethod('POST')) {
                $form->setData($this->setUserInformation($form->getData(), $userInformation));
            }

            return $processed;
        }

        $user = $this->userManager->createUser();
        $user->setEnabled(true);

        $form->setData($this->setUserInformation($user, $userInformation));

        if ($request->isMethod('POST')) {
            $form->bind($request);

            return $form->isValid();
        }

        return false;
    }

    /**
     * Set registration form handler.
     *
     * @param null|RegistrationFormHandler $registrationFormHandler FOSUB registration form handler
     */
    public function setFormHandler(RegistrationFormHandler $registrationFormHandler = null)
    {
        $this->registrationFormHandler = $registrationFormHandler;
    }

    /**
     * Attempts to get a unique username for the user.
     *
     * @param string $name
     *
     * @return string Name, or empty string if it failed after all the iterations.
     */
    protected function getUniqueUserName($name)
    {
        $i = 0;
        $testName = $name;

        do {
            $user = $this->userManager->findUserByUsername($testName);
        } while ($user !== null && $i < $this->iterations && $testName = $name.++$i);

        return $user !== null ? '' : $testName;
    }

    /**
     * Reconstructs the form handler in order to inject the right form.
     *
     * @param Request $request Active request
     * @param Form    $form    Form to process
     *
     * @return mixed
     */
    protected function reconstructFormHandler(Request $request, Form $form)
    {
        $handlerClass = get_class($this->registrationFormHandler);

        return new $handlerClass($form, $request, $this->userManager, $this->mailer, $this->tokenGenerator);
    }

    /**
     * Set user information from form
     *
     * @param UserInterface         $user
     * @param UserResponseInterface $userInformation
     *
     * @return UserInterface
     */
    protected function setUserInformation(UserInterface $user, UserResponseInterface $userInformation)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $accessor->setValue($user, 'username', $this->getUniqueUserName($userInformation->getEmail()));

        if ($accessor->isWritable($user, 'email')) {
            $accessor->setValue($user, 'email', $userInformation->getEmail());
        }

        // Nombre del usuario partido
        $nombresUsuario = explode(" ", $userInformation->getRealname(), 2);

        // Setteando saldo en 0 para el nuevo usuario
        $accessor->setValue($user, 'saldo', 1);

        // Nombre del usuario
        $accessor->setValue($user, 'nombre', $nombresUsuario[0]);

        // Apellidos del usuario
        $accessor->setValue($user, 'apellidos', $nombresUsuario[1]);

        // Moneda del usuario
        $ip = $this->getIP();
        $details = json_decode(file_get_contents("http://ipinfo.io/".$ip."/json"));
        $accessor->setValue($user, 'moneda', $this->getCurrency($details->country));
        
        return $user;
    }

    protected function getIP() 
    {
        if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $real_client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            $real_client_ip = $_SERVER["REMOTE_ADDR"];
        }
        return "192.100.196.185";
    }

    protected function getCurrency($country_code)
    {
        switch ($country_code) {
            case 'MX':
                $currency = "MXN";
                break;
            
            default:
                $currency = "MXN";
                break;
        }
        return $currency;
    }
}
