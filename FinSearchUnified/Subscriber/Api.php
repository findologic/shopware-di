<?php

namespace FinSearchUnified\Subscriber;

use Enlight\Event\SubscriberInterface;

class Api implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Api_Findologic' => 'onFindologicApiController',
        ];
    }

    public function onFindologicApiController(\Enlight_Event_EventArgs $args)
    {
        return $this->Path().'Controllers/Api/Findologic.php';
    }
}