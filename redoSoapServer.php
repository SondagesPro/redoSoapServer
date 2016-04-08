<?php
/**
 * Redo the SoapSrver of 1.87
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 0.1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
ini_set("soap.wsdl_cache_enabled", "0");

class redoSoapServer extends PluginBase
{
    protected $storage = 'DbStorage';
    static protected $description = 'Redo the soapServer of LimeSurvey 1.87 (partial), use ssl by default, deactivate ssl in global settings to deactivate.';
    static protected $name = 'redoSoapServer';

    private $sSessionKey;
    protected $settings = array(
        'information' => array(
            'type' => 'info',
            'content' => 'updated',
        ),
        'baseurl' => array(
            'type'=>'string',
            'label' => 'Base complete url for the wsdl (without the final / )',
            'default'=>'',
            'htmlOptions'=>array(
                'placeholder'=>'http://example.org/surveys'
            ),
            'help'=>'Remind to force a new wsdl file after update.',
        ),
    );
    /**
    * Add function to be used in newDirectRequest/newUnsecureRequest event
    */
    public function init()
    {
        $this->subscribe('newDirectRequest','actionRequest');
        /* @todo : control in unsecureRequest exist */
        //$this->subscribe('newUnsecureRequest','actionRequest');
        $this->subscribe('beforeActivate');
    }

    public function beforeActivate()
    {
        $oEvent = $this->event;
        $this->actionDoWsdl();
    }

    public function actionRequest()
    {
        $oEvent = $this->event;
        $sAction=$oEvent->get('function');
        if ($oEvent->get('target') == "redoSoapServer")
        {

            if(!is_null(Yii::app()->request->getQuery("wsdl")))
                $this->actionDoWsdl();
            else
                $this->actionDefault();
        }
    }

    public function actionDoWsdl()
    {
        $wsdlString = file_get_contents(dirname(__FILE__) . "/lsrc_base0.wsdl");
        $baseUrl=$this->createParamAbsoluteUrl();
        $wsdlString = str_replace("{lsrclocation}",$baseUrl,$wsdlString);
        file_put_contents(dirname(__FILE__) . "/lsrc.wsdl",$wsdlString);
        if(Yii::app()->request->getQuery("wsdl"))
        {
            header('Content-type: text/wsdl');
            header('Content-Disposition: attachment; filename=lsrc.wsdl');
            echo $wsdlString;
        }
        elseif(!is_null(Yii::app()->request->getQuery("wsdl")))
        {
            header('Content-type: text/wsdl');
            echo $wsdlString;
        }
    }

    public function actionDefault()
    {
        Yii::import('application.helpers.viewHelper');
        viewHelper::disableHtmlLogging();
        if(!is_file(dirname(__FILE__) . "/lsrc.wsdl"))
            $this->actionDoWsdl();
        require_once(dirname(__FILE__) . '/soapFunction.php');
        $wsdl=Yii::app()->request->getBaseUrl(true)."/plugins/redoSoapServer/lsrc.wsdl";
        $server = new SoapServer($wsdl, array('soap_version' => SOAP_1_1));
        $server->addFunction("sInsertParticipants");
        $server->handle();

    }

    public function getPluginSettings($getValues=true)
    {
        $urlDowload=App()->createUrl('plugins/direct', array('plugin' => $this->getName(), 'function' => 'wsdl','wsdl'=>1),'&amp;');
        $urlDo=App()->createUrl('plugins/direct', array('plugin' => $this->getName(), 'function' => 'wsdl','wsdl'=>1),'&amp;');
        $urlAuto=$this->api->createUrl('plugins/direct', array('plugin' => $this->getName(), 'function' => 'auto'),'&amp;');
        $wsdlFile=Yii::app()->request->getBaseUrl(true)."/plugins/redoSoapServer/lsrc.wsdl";
        $this->settings['information']['content']= "<p class='alert alert-info'>To force and download a new wsdl file : <a href='{$urlDowload}'>{$urlDowload}</a></p>";
        $this->settings['information']['content']= "<p class='alert alert-info'>To force and see a new wsdl file : <a href='{$urlDo}'>{$urlDo}</a></p>";
        $this->settings['information']['content'].= "<p class='alert alert-info'>The wsdl file is here : <a href='{$wsdlFile}'>{$wsdlFile}</a></p>";
        $this->settings['information']['content'].= "<p class='alert alert-info'>The action file with actual link : <a href='{$urlAuto}'>{$urlAuto}</a></p>";
        $urlSet=$this->get('baseurl');
        if(!empty($urlSet))
        {
            $urlSet=$this->createParamAbsoluteUrl();
            $this->settings['information']['content'].= "<p class='alert alert-info'>The action file with parameters link : <a href='{$urlSet}'>{$urlSet}</a></p>";
        }
        return parent::getPluginSettings($getValues);
    }

    /**
     * get the parameters URL
     */
    public function createParamAbsoluteUrl()
    {
        $route='plugins/direct';
        $params=array('function' => 'auto');
        $schema = (App()->getConfig('force_ssl')=="on") ? "https" : "http";
        $ampersand='&amp;';
        $sPublicUrl=$this->get('baseurl');
        // Control if public url are really public : need scheme and host
        // If yes: use it
        $aPublicUrl=parse_url($sPublicUrl);
        if(isset($aPublicUrl['scheme']) && isset($aPublicUrl['host']))
        {
            $url=App()->createAbsoluteUrl($route,$params);
            $sActualBaseUrl=App()->getBaseUrl(true);
            if (substr($url, 0, strlen($sActualBaseUrl)) == $sActualBaseUrl) {
                $url = substr($url, strlen($sActualBaseUrl));
            }
            return trim($sPublicUrl,"/").$url;
        }
        else
        {
            $schema = (App()->getConfig('force_ssl')=="off") ? "http" : "https";
            return App()->createAbsoluteUrl($route,$params,$schema,$ampersand);
        }
    }
}
