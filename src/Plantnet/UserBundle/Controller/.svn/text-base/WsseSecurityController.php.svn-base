<?php

/**
 * This file is part of the Identify package.
 *
 * (c) Julien Barbe <julien.barbe@cirad.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Plantnet\UserBundle\Controller;

use Plantnet\UserBundle\Document\User;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations\Prefix;
use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\View\RouteRedirectView;
use FOS\RestBundle\View\View AS FOSView;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

/**
 * Controller that provides Restfuls security functions.
 *
 * @Prefix("/security")
 * @NamePrefix("plantnet_securityrest_")
 * @author Julien Barbe <julien.barbe@me.com>
 */
class WsseSecurityController extends Controller
{

    /**
     * WSSE Token generation
     *
     * @return FOSView
     * @ApiDoc()
     */
    public function postTokenCreateAction()
    {

        $view = FOSView::create();
        $request = $this->getRequest();

        $username = $request->get('_username');
        $password = $request->get('_password');

        $um = $this->get('fos_user.user_manager');
        $user = $um->findUserByUsernameOrEmail($username);

        $created = date('c');
        $nonce = substr(md5(uniqid('nonce_', true)), 0, 16);
        $nonceHigh = base64_encode($nonce);    

        if ($user instanceof User) {
          // Get the encoder for the users password
          $encoder_service = $this->get('security.encoder_factory');
          $encoder = $encoder_service->getEncoder($user);
          $encoded_pass = $encoder->encodePassword($password, $user->getSalt());

          if ($user->getPassword() == $encoded_pass) {
            //$passwordDigest = base64_encode(sha1($nonce . $created . $password . "{".$user->getSalt()."}", true));
            $passwordDigest = base64_encode(sha1($nonce . $created . $user->getPassword(), true));
            $header = "UsernameToken Username=\"{$username}\", PasswordDigest=\"{$passwordDigest}\", Nonce=\"{$nonceHigh}\", Created=\"{$created}\"";
            $view->setHeader("Authorization", 'WSSE profile="UsernameToken"');
            $view->setHeader("X-WSSE", "UsernameToken Username=\"{$username}\", PasswordDigest=\"{$passwordDigest}\", Nonce=\"{$nonceHigh}\", Created=\"{$created}\"");
            $data = array('WSSE' => $header);
            $view->setStatusCode(200)->setData($data);
          } else {
            $view->setStatusCode(403)->setData("Bad credentials");
          }
        } else {
          $view->setStatusCode(403)->setData("Unknown username");
        }

        
        return $view;
    }

  /**
     * WSSE Token Remove
     *
     * @return FOSView
     * @ApiDoc()
     */
    public function getTokenDestroyAction()
    {
        $view = FOSView::create();
        $security = $this->get('security.context');
        $token = new AnonymousToken(null, new User());
        $security->setToken($token);
        $this->get('session')->invalidate();
        $view->setStatusCode(200)->setData('Logout successful');
        return $view;
    }
}
