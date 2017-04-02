<?php

namespace Redis\RSMQWorkerBundle\Twig;


class TwigExtension extends \Twig_Extension
{

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('redis_rsmq_worker_render_js', array($this, 'renderJs'), array('is_safe' => array('html'))),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'redis_rsmq_worker';
    }


    public function renderJs()
    {
        $out = '<script type="text/javascript" src="/public/bower_components/socket.io-client/dist/socket.io.js"></script>';
        $out .= '<script src="/bundles/rsmqworker/js/client.js" type="text/javascript"></script>';
        return $out;
    }
}
