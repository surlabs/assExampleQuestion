<?php

include_once "./Modules/TestQuestionPool/classes/class.ilQuestionsPlugin.php";
	
/**
* Question plugin Example
*
* @author Fred Neumann <frd.neumann@fau.de>
* @version $Id$
* @ingroup ModulesTestQuestionPool
*/
class ilassExampleQuestionPlugin extends ilQuestionsPlugin
{
		final function getPluginName()
		{
			return "assExampleQuestion";
		}
		
		final function getQuestionType()
		{
			return "assExampleQuestion";
		}
		
		final function getQuestionTypeTranslation()
		{
			return $this->txt($this->getQuestionType());
		}
}
?>