<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * The soap global Function
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 0.0.1
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

/**
 * Function to insert multiple partcipant in token table
 * @param string $sUser : the username
 * @param string $sPass: The user password
 * @param interger $iSurveyId : the survey identifier
 * @param string $sParticipantData : the participant data
 */
function sInsertParticipants($sUser, $sPass, $iSurveyId, $sParticipantData)
{
    soapFunctionLogin($sUser, $sPass);
    validateSurvey($iSurveyId);
    if(!Permission::model()->hasSurveyPermission($iSurveyId, 'tokens', 'create'))
    {
        throw new SoapFault("Authentication: ", "This user don't have right to create token for this survey");
        exit;
    }
    // Create token table if not exist
    $bTokenExists = tableExists('{{tokens_' . $iSurveyId . '}}');
    if(!$bTokenExists)
    {
        Token::createTable($iSurveyId);
    }
    //set the Seperators to default if nothing is set in the lsrc.config.php
    if(empty($sDatasetSeperator))
        {$sDatasetSeperator = "::";}
    if(empty($sDatafieldSeperator))
        {$sDatafieldSeperator = ";";}
    $oSurvey=Survey::model()->findByPk($iSurveyId);
    $asDataset = explode($sDatasetSeperator, $sParticipantData);
    $iCountParticipants =  count($asDataset);
    $iInsertedParticipants=0;
    $iExistingParticipants=0;
    $iErrorParticipants=0;
    $aColumnNames=array_keys(Yii::app()->db->schema->getTable('{{tokens_'.$iSurveyId.'}}')->columns);
$trace="";
    foreach($asDataset as $sData)
    {

        if(!empty($sData))
        {
            $asDatafield = explode($sDatafieldSeperator, $sData);
            $bTokenExist=false;
            if(!empty($asDatafield[4])) // Token
            {
                $oExistingToken = Token::model($iSurveyId)->count("token=:token", array("token" => $asDatafield[4]));
                if($oExistingToken)
                {
                    $bTokenExist=true;
                }
            }
            if(!$bTokenExist)
            {
                //~ // Create attribute column if needed
                if(!empty($asDatafield[7]))
                {
                    $aAttributesToken=explode(",", $asDatafield[7]);
                    $iNeededAttributes=count($aAttributesToken)+1;
                    $bTableUpdated=false;
                    for ($count = 1; $count < $iNeededAttributes; $count++)
                    {
                        while (!in_array('attribute_' . $count, $aColumnNames))
                        {
                            Yii::app()->db->createCommand(Yii::app()->db->getSchema()->addColumn("{{tokens_".$iSurveyId."}}", 'attribute_' . $count, 'string(255)'))->execute();
                            $aColumnNames[]='attribute_' . $count;
                            $bTableUpdated=true;
                        }
                    }
                    if($bTableUpdated)
                    {
                        Yii::app()->db->schema->getTable('{{tokens_' . $iSurveyId . '}}', true); // Refresh schema cache
                    }
                }
                $oToken = Token::create($iSurveyId);
                if(!empty($asDatafield[0]))
                {
                    $oToken->firstname=$asDatafield[0];
                }
                if(!empty($asDatafield[1]))
                {
                    $oToken->lastname=$asDatafield[1];
                }
                if(!empty($asDatafield[2]))
                {
                    $oToken->email=$asDatafield[2];
                }
                if(!empty($asDatafield[3])) // language
                {
                    $oToken->language=$asDatafield[3];
                }
                else
                {
                    $oToken->language=$oSurvey->language;
                }
                if(!empty($asDatafield[4])) // token
                {
                    $oToken->token=$asDatafield[4];
                }
                if(!empty($asDatafield[5]))
                {
                    $oToken->validfrom=$asDatafield[5];
                }
                if(!empty($asDatafield[6]))
                {
                    $oToken->validuntil=$asDatafield[6];
                }
                if(!empty($asDatafield[7]))
                {
                    for ($count = 1; $count < $iNeededAttributes; $count++)
                    {
                        $attribute="attribute_$count";
                        $oToken->$attribute=$aAttributesToken[$count-1];
                    }
                }
                if($oToken->validate())
                {
                    $oToken->save();
                    $iInsertedParticipants++;
                }
                else
                {
                    $iErrorParticipants++;
                }
            }
            else
            {
                $iExistingParticipants++;
            }
        }
    }
    soapFunctionLogout();
    return "$iCountParticipants Datasets given, $iInsertedParticipants rows inserted. $trace";
}


/**
 * Set the session to get rights
 * @param string $sUser : User name
 * @param string $sPass : Clear password
 * @return void
 */
function soapFunctionLogin($sUser, $sPass)
{
    $identity = new UserIdentity(sanitize_user($sUser), $sPass);
    if (!$identity->authenticate())
    {
        throw new SoapFault("Authentication: ", "User or password wrong");
        exit;
    }
    $oUser = User::model()->find('users_name=:users_name',array('users_name' => $sUser));

    Yii::app()->session['loginID']=$oUser->uid;
    Yii::app()->user->setId($oUser->uid);
}
/**
 * Log out to clean up file
 * @param string $sUser : User name
 * @param string $sPass : Clear password
 * @return void
 */
function soapFunctionLogout()
{
    App()->user->logout();
    Yii::app()->session['loginID']=null;
}

/**
 * Simple validation of survey
 * @param int $iSurveyId : survey ident
 * @return void
 */
function validateSurvey($iSurveyId)
{
    // check if $iSurveyId is set, else abort
    if(empty($iSurveyId))
    {
        throw new SoapFault("Server: ", "No SurveyId given");
        exit;
    }
    $oSurvey=Survey::model()->findByPk($iSurveyId);
    if(!$oSurvey)
    {
        throw new SoapFault("Server: ", "SurveyId don't exist");
        exit;
    }
}

function createTokenTable($iSurveyId)
{

}
