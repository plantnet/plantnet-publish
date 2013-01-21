<?php

namespace Plantnet\UserBundle\Security;
 
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Request;
 
class WsseHeaderRequestMatcher implements RequestMatcherInterface
{
    public function matches(Request $request)
    {
        return $request->headers->has('x-wsse');
    }
}