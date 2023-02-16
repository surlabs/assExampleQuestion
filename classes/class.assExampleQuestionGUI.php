<?php

/**
 * Example GUI class for question type plugins
 *
 * @author	Fred Neumann <fred.neumann@fau.de>
 * @version	$Id:  $
 * @ingroup ModulesTestQuestionPool
 *
 * @ilctrl_iscalledby assExampleQuestionGUI: ilObjQuestionPoolGUI, ilObjTestGUI, ilQuestionEditGUI, ilTestExpressPageObjectGUI
 * @ilctrl_calls assExampleQuestionGUI: ilFormPropertyDispatchGUI
 */
class assExampleQuestionGUI extends assQuestionGUI
{
	/**
	 * @var ilassExampleQuestionPlugin	The plugin object
	 */
	var $plugin = null;


	/**
	 * @var assExampleQuestion	The question object
	 */
	public assQuestion $object;
	
	/**
	* Constructor
	*
	* @param integer $id The database id of a question object
	* @access public
	*/
	public function __construct($id = -1)
	{
		global $DIC;

		parent::__construct();

		/** @var ilComponentFactory $component_factory */
		$component_factory = $DIC["component.factory"];
		$this->plugin = $component_factory->getPlugin('exmqst');
		$this->object = new assExampleQuestion();
		if ($id >= 0)
		{
			$this->object->loadFromDb($id);
		}
	}

	/**
	 * Creates an output of the edit form for the question
	 *
	 * @param bool $checkonly
	 * @return bool
	 */
	public function editQuestion($checkonly = false)
	{
		global $DIC;
		$lng = $DIC->language();

		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->outQuestionType());
		$form->setMultipart(TRUE);
		$form->setTableWidth("100%");
		$form->setId("exmqst");

		// Title, author, description, question, working time
		$this->addBasicQuestionFormProperties($form);

		// Here you can add question type specific form properties
		// We only add an input field for the maximum points
		// NOTE: in complex question types the maximum points are summed up by partial points
		$points = new ilNumberInputGUI($lng->txt('maximum_points'),'points');
		$points->setSize(3);
		$points->setMinValue(1);
		$points->allowDecimals(0);
		$points->setRequired(true);
		$points->setValue($this->object->getPoints());
		$form->addItem($points);

		$this->populateTaxonomyFormSection($form);
		$this->addQuestionFormCommandButtons($form);

		$errors = false;
		if ($this->isSaveCommand())
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			$form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
			if ($errors)
			{
				$checkonly = false;
			}
		}

		if (!$checkonly)
		{
			$this->getQuestionTemplate();
			$this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
		}

		return $errors;
	}

	/**
	 * Evaluates a posted edit form and writes the form data in the question object
	 *
	 * @param bool $always
	 * @return integer A positive value, if one of the required fields wasn't set, else 0
	 */
	protected function writePostData($always = false): int
	{
		$hasErrors = (!$always) ? $this->editQuestion(true) : false;
		if (!$hasErrors)
		{
			$this->writeQuestionGenericPostData();

			// Here you can write the question type specific values
			// Some question types define the maximum points directly,
			// other calculate them from other properties
			$this->object->setPoints((int) $_POST["points"]);

			$this->saveTaxonomyAssignments();
			return 0;
		}
		return 1;
	}


	/**
	 * Get the HTML output of the question for a test
	 * (this function could be private)
	 * 
	 * @param integer $active_id						The active user id
	 * @param integer $pass								The test pass
	 * @param boolean $is_postponed						Question is postponed
	 * @param boolean $use_post_solutions				Use post solutions
	 * @param boolean $show_specific_inline_feedback	Show a specific inline feedback
	 * @return string
	 */
	public function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_specific_inline_feedback = FALSE): string
	{
		if (is_null($pass))
		{
			$pass = ilObjTest::_getPass($active_id);
		}

		$solution = $this->object->getSolutionStored($active_id, $pass, null);
		$value1 = isset($solution["value1"]) ? $solution["value1"] : "";
		$value2 = isset($solution["value2"]) ? $solution["value2"] : "";

		// fill the question output template
		// in out example we have 1:1 relation for the database field
		$template = $this->plugin->getTemplate("tpl.il_as_qpl_exmqst_output.html");

		$template->setVariable("QUESTION_ID", $this->object->getId());
		$questiontext = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		$template->setVariable("LABEL_VALUE1", $this->plugin->txt('label_value1'));
		$template->setVariable("LABEL_VALUE2", $this->plugin->txt('label_value2'));

		$template->setVariable("VALUE1", ilLegacyFormElementsUtil::prepareFormOutput($value1));
		$template->setVariable("VALUE2", ilLegacyFormElementsUtil::prepareFormOutput($value2));

		$questionoutput = $template->get();
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;
	}

	
	/**
	 * Get the output for question preview
	 * (called from ilObjQuestionPoolGUI)
	 * 
	 * @param boolean	$show_question_only 	show only the question instead of embedding page (true/false)
	 * @param boolean	$show_question_only
	 * @return string
	 */
	public function getPreview($show_question_only = FALSE, $showInlineFeedback = FALSE)
	{
		if( is_object($this->getPreviewSession()) )
		{
			$solution = $this->getPreviewSession()->getParticipantsSolution();
		}
		else
		{
			$solution = array('value1' => null, 'value2' => null);
		}

		// Fill the template with a preview version of the question
		$template = $this->plugin->getTemplate("tpl.il_as_qpl_exmqst_output.html");
		$questiontext = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		$template->setVariable("QUESTION_ID", $this->object->getId());
		$template->setVariable("LABEL_VALUE1", $this->plugin->txt('label_value1'));
		$template->setVariable("LABEL_VALUE2", $this->plugin->txt('label_value2'));

		$template->setVariable("VALUE1", ilLegacyFormElementsUtil::prepareFormOutput($solution['value1'] ?? ''));
		$template->setVariable("VALUE2", ilLegacyFormElementsUtil::prepareFormOutput($solution['value2'] ?? ''));

		$questionoutput = $template->get();
		if(!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		return $questionoutput;
	}

	/**
	 * Get the question solution output
	 * @param integer $active_id             The active user id
	 * @param integer $pass                  The test pass
	 * @param boolean $graphicalOutput       Show visual feedback for right/wrong answers
	 * @param boolean $result_output         Show the reached points for parts of the question
	 * @param boolean $show_question_only    Show the question without the ILIAS content around
	 * @param boolean $show_feedback         Show the question feedback
	 * @param boolean $show_correct_solution Show the correct solution instead of the user solution
	 * @param boolean $show_manual_scoring   Show specific information for the manual scoring output
	 * @param bool    $show_question_text

	 * @return string solution output of the question as HTML code
	 */
	function getSolutionOutput(
		$active_id,
		$pass = NULL,
		$graphicalOutput = FALSE,
		$result_output = FALSE,
		$show_question_only = TRUE,
		$show_feedback = FALSE,
		$show_correct_solution = FALSE,
		$show_manual_scoring = FALSE,
		$show_question_text = TRUE
	): string
	{
		// get the solution of the user for the active pass or from the last pass if allowed
		if (($active_id > 0) && (!$show_correct_solution))
		{
			$solution = $this->object->getSolutionStored($active_id, $pass, true);
			$value1 = isset($solution["value1"]) ? $solution["value1"] : "";
			$value2 = isset($solution["value2"]) ? $solution["value2"] : "";
		}
		else
		{
			// show the correct solution
			$value1 =  $this->plugin->txt("any_text");
			$value2 = $this->object->getMaximumPoints();
		}

		// get the solution template
		$template = $this->plugin->getTemplate("tpl.il_as_qpl_exmqst_output_solution.html");
		$solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", TRUE, TRUE, "Modules/TestQuestionPool");

		if (($active_id > 0) && (!$show_correct_solution))
		{
			if ($graphicalOutput)
			{
				// copied from assNumericGUI, yet not really understood
				if($this->object->getStep() === NULL)
				{
					$reached_points = $this->object->getReachedPoints($active_id, $pass);
				}
				else
				{
					$reached_points = $this->object->calculateReachedPoints($active_id, $pass);
				}

				// output of ok/not ok icons for user entered solutions
				// in this example we have ony one relevant input field (points)
				// so we just need to set the icon beneath this field
				// question types with partial answers may have a more complex output
				if ($reached_points == $this->object->getMaximumPoints())
				{
					$template->setCurrentBlock("icon_ok");
					$template->setVariable("ICON_OK", ilUtil::getImagePath("icon_ok.svg"));
					$template->setVariable("TEXT_OK", $this->lng->txt("answer_is_right"));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock("icon_ok");
					$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_not_ok.svg"));
					$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_wrong"));
					$template->parseCurrentBlock();
				}
			}
		}

		// fill the template variables
		// adapt this to your structure of answers
		$template->setVariable("LABEL_VALUE1", $this->plugin->txt('label_value1'));
		$template->setVariable("LABEL_VALUE2", $this->plugin->txt('label_value2'));

		$template->setVariable("VALUE1", empty($value1) ? "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" : ilLegacyFormElementsUtil::prepareFormOutput($value1));
		$template->setVariable("VALUE2", empty($value2) ? "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" : ilLegacyFormElementsUtil::prepareFormOutput($value2));

		$questiontext = $this->object->getQuestion();
		if ($show_question_text==true)
		{
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($questiontext, TRUE));
		}

		$questionoutput = $template->get();

		$feedback = ($show_feedback && !$this->isTestPresentationContext()) ? $this->getGenericFeedbackOutput($active_id, $pass) : "";
		if (strlen($feedback))
		{
			$cssClass = ( $this->hasCorrectSolution($active_id, $pass) ?
				ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_CORRECT : ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_WRONG
			);

			$solutiontemplate->setVariable("ILC_FB_CSS_CLASS", $cssClass);
			$solutiontemplate->setVariable("FEEDBACK", $this->object->prepareTextareaOutput( $feedback, true ));

		}
		$solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);

		$solutionoutput = $solutiontemplate->get();
		if(!$show_question_only)
		{
			// get page object output
			$solutionoutput = $this->getILIASPage($solutionoutput);
		}
		return $solutionoutput;
	}

	/**
	 * Returns the answer specific feedback for the question
	 * 
	 * @param array $userSolution Array with the user solutions
	 * @return string HTML Code with the answer specific feedback
	 * @access public
	 */
	public function getSpecificFeedbackOutput($userSolution): string
	{
		// By default no answer specific feedback is defined
		$output = '';
		return $this->object->prepareTextareaOutput($output, TRUE);
	}
	
	
	/**
	* Sets the ILIAS tabs for this question type
	* called from ilObjTestGUI and ilObjQuestionPoolGUI
	*/
	public function setQuestionTabs(): void
	{
		parent::setQuestionTabs();
	}
}
?>
