<?php
/**
 * @author ReRe Design studio
 * @email webmaster@rere-design.ru
 */

class BitrixModule extends CWebModule {
    public function init()
    {
        $this->fixPhpAuth();  // Add RewriteRule .* - [E=REMOTE_USER:%{HTTP:Authorization},L] in .htaccess

        YiiBase::setPathOfAlias('bitrix', YiiBase::getPathOfAlias('application.modules.bitrix'));

        $imports = array(
            'bitrix.models.*',
            'bitrix.controllers.*',
        );
        Yii::app()->errorHandler->errorAction = $this->id.'/default/error';
        Yii::app()->log->routes['web']->enabled = false;
        $this->setImport($imports);
        parent::init();
    }

    public function fixPhpAuth()
    {
        $remote_user = $_SERVER["REMOTE_USER"]
            ? $_SERVER["REMOTE_USER"] : $_SERVER["REDIRECT_REMOTE_USER"];
        $strTmp = base64_decode(substr($remote_user,6));
        if ($strTmp)
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $strTmp);
    }
}