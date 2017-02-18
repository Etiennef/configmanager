<?php
include ("../../../inc/includes.php");

if (isset($_POST['update']) && isset($_POST['config__object_name']) && class_exists($_POST['config__object_name'])) {
   $config = new $_POST['config__object_name']();
   if (!$config instanceof PluginConfigmanagerRule) {
      // Not translation because can't happend unless 'hack' or programmation error
      Session::addMessageAfterRedirect("Unable to intanciate config class " . $_POST['config__object_name']);
      Html::back();
   }

   $config->checkAll($_POST);
   $config->updateAll($_POST);
}

Html::back();