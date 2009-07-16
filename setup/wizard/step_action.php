<?php
/**
* Steap Action Controller.
*
* KnowledgeTree Community Edition
* Document Management Made Simple
* Copyright (C) 2008,2009 KnowledgeTree Inc.
* Portions copyright The Jam Warehouse Software (Pty) Limited
*
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU General Public License version 3 as published by the
* Free Software Foundation.
*
* This program is distributed in the hope that it will be useful, but WITHOUT
* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
* details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* You can contact KnowledgeTree Inc., PO Box 7775 #87847, San Francisco,
* California 94120-7775, or email info@knowledgetree.com.
*
* The interactive user interfaces in modified source and object code versions
* of this program must display Appropriate Legal Notices, as required under
* Section 5 of the GNU General Public License version 3.
*
* In accordance with Section 7(b) of the GNU General Public License version 3,
* these Appropriate Legal Notices must retain the display of the "Powered by
* KnowledgeTree" logo and retain the original copyright notice. If the display of the
* logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
* must display the words "Powered by KnowledgeTree" and retain the original
* copyright notice.
*
* @copyright 2008-2009, KnowledgeTree Inc.
* @license GNU General Public License version 3
* @author KnowledgeTree Team
* @package Installer
* @version Version 0.1
*/

class stepAction {
	/**
	* Step class name
	*
	* @author KnowledgeTree Team
	* @access protected
	* @var string
	*/
    protected $stepName = '';

	/**
	* Step names for classes
	*
	* @author KnowledgeTree Team
	* @access protected
	* @var array
	*/
    protected $stepClassNames = array();

	/**
	* Flag if step needs confirmation
	*
	* @author KnowledgeTree Team
	* @access protected
	* @var boolean
	*/
    protected $displayConfirm = false;

	/**
	* Reference to session object
	*
	* @author KnowledgeTree Team
	* @access protected
	* @var object Session
	*/
    protected $session = null;

	/**
	* Reference to current step object
	*
	* @author KnowledgeTree Team
	* @access protected
	* @var object class Step
	*/
    protected $action = null;

	/**
	* Constructs step action object
	*
	* @author KnowledgeTree Team
	* @access public
	* @param string class name of the current step
 	*/
    public function __construct($step) {
        $this->stepName = $step;
    }

	/**
	* Main control to handle the steps actions
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return string
	*/
    public function doAction() {
        if($this->stepName != '') {
			$this->action = $this->createStep();
			if(!$this->action) {
				die("{$this->stepName} : Class Files Missing");
			}
            $response = $this->action->doStep();
            if($this->action->storeInSession()) { // Check if class values need to be stored in session
            	$this->loadStepToSession($this->stepName); // Send class to session
            }
            if ($response == 'error') {
            	$this->handleErrors(); // Send Errors to session
            } else {
            	$this->clearErrors($this->stepName); // Send Errors to session
            }
            return $response;
        } else {
            die("{$this->stepName} : Class Files Missing");
        }
    }

	/**
	* Instantiate a step.
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return object Step
	*/
    public function createStep() {
    	$filename = STEP_DIR."{$this->stepName}.php";
    	if (file_exists($filename)) {
			require_once($filename);
			$step_class = $this->makeCamelCase($this->stepName);
			return new $step_class();
    	}
		return false;
    }

	/**
	* Converts string to camel case
	*
	* @author KnowledgeTree Team
	* @param string
	* @access public
	* @return string
	*/
    public function makeCamelCase($str) {
        $upper=ucwords($str);
        $str=str_replace('_', '', $upper);

        return $str;
    }

	/**
	* Converts string to human readable heading
	*
	* @author KnowledgeTree Team
	* @param string
	* @access public
	* @return string
	*/
    public function makeHeading($str) {
        $str = str_replace('_', ' ', $str);
        $str = ucwords($str);

        return $str;
    }

	/**
	* Sets steps class names in string format
	*
	* @author KnowledgeTree Team
	* @param array
	* @access public
	* @return void
	*/
    public function setSteps($stepClassNames) {
        $this->stepClassNames = $stepClassNames;
    }

	/**
	* Sets steps in human readable string format
	*
	* @author KnowledgeTree Team
	* @param array
	* @access public
	* @return void
	*/
    public function setStepNames($step_names) {
        $this->step_names = $step_names;
    }

	/**
	* Returns a message to display at the top of template
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return string
	*/
    public function getTop() {
        return '<span class="top">'.$this->getCurrentStepName().'</span>';
    }

	/**
	* Returns current step name
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return string
	*/
    function getCurrentStepName() {
        return$this->step_names[$this->stepName];
    }

	/**
	* Returns left menu
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return string
	*/
    public function getLeftMenu()
    {
        $menu = '<div class="menu">';
        $active = false;

        foreach ($this->stepClassNames as $k=>$step){
            if($this->step_names[$step] != '') {
                $item = $this->step_names[$step];
            } else {
                $item = $this->makeHeading($step);
            }
            if($step == $this->stepName) {
                $class = 'active';
                $active = true;
            } else {
                if($active){
                    $class = 'inactive';
                }else{
                    $class = 'indicator';
                    $item = "<a href=\"index.php?step_name={$step}\">{$item}</a>";
                }
            }

            $menu .= "<span class='{$class}'>$item</span><br />";
        }
        $menu .= '</div>';
        return $menu;
    }

	/**
	* Returns confirmation page flag
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return boolean
	*/
    function displayConfirm() {
    	// TODO:No other way I can think of doing this
        return $this->displayConfirm;
    }

	/**
	* Sets confirmation page flag
	*
	* @author KnowledgeTree Team
	* @param boolean
	* @access public
	* @return void
	*/
    function setDisplayConfirm($displayConfirm) {
        $this->displayConfirm = $displayConfirm;
    }

	/**
	* Sets session object
	*
	* @author KnowledgeTree Team
	* @param object Session
	* @access public
	* @return void
	*/
    function loadSession($ses) {
        $this->session = $ses;
    }

	/**
	* Returns session object
	*
	* @author KnowledgeTree Team
	* @param object Session
	* @access public
	* @return void
	*/
    function getSession() {
        return $this->session;
    }

	/**
	* Returns step tenplate content
	*
	* @author KnowledgeTree Team
	* @param none
	* @access public
	* @return string
	*/
    function paintAction() {
        $left = $this->getLeftMenu();
        $top = $this->getTop();
        $step_errors = $this->action->getErrors(); // Get errors
        if($this->displayConfirm()) // Check if theres a confirm step
            $template = "templates/{$this->stepName}_confirm.tpl";
        else
            $template = "templates/{$this->stepName}.tpl";
        $step_tpl = new Template($template);
        $step_tpl->set("errors", $step_errors); // Set template errors
        $step_vars = $this->action->getStepVars(); // Get template variables
        foreach ($step_vars as $key => $value) { // Set template variables
            $step_tpl->set($key, $value); // Load values to session
            if($this->action->storeInSession()) { // Check if class values need to be stored in session
            	$this->loadValueToSession($this->stepName, $key, $value);
            }
        }
        $content = $step_tpl->fetch();
		$tpl = new Template("templates/wizard.tpl");
		$tpl->set('content', $content);
		$tpl->set('left', $left);
		echo $tpl->fetch();
	}

    /**
     * Load class to session
     *
     * @author KnowledgeTree Team
     * @param string $class name of class
     * @param array $v array of values
     * @param boolean $overwrite whether or not to overwrite existing
     * @access private
     * @return void
     */
     private function loadStepToSession($class, $v = array(), $overwrite = false) {
         if($this->session != null) {
             if($overwrite) {
                 $this->session->set($class , $v);
             } else {
                 if(!$this->session->is_set($class))
                    $this->session->set($class , $v);
            }
         } else {
             die("Where is the session");
         }
     }

    /**
     * Load class value to session
     *
     * @author KnowledgeTree Team
     * @param string $class name of class
     * @param string $k key value
     * @param string $v value to store
     * @param boolean $overwrite whether or not to overwrite existing
     * @access private
     * @return void
     */
     private function loadValueToSession($class, $k, $v, $overwrite = false) {
         if($this->session != null) {
            $this->session->setClass($class, $k, $v);
         } else {
             die("Where is the session");
         }
     }

    /**
     * Load all class errors value to session
     *
     * @author KnowledgeTree Team
     * @param none
     * @access private
     * @return void
     */
     private function handleErrors() {// TODO: handle multiple errors
        $step_errors = $this->action->getErrors(); // Get errors
        foreach ($step_errors as $key => $value) {
            $this->loadErrorToSession($this->stepName, $key, $value); // Load values session
        }
     }

   /**
     * Remove all class errors value to session
     *
     * @author KnowledgeTree Team
     * @param none
     * @access private
     * @return void
     */
     private function clearErrors($class) {
     	$this->session->clearErrors($class);
     }

    /**
     * Load class error value to session
     *
     * @author KnowledgeTree Team
     * @param string $class name of class
     * @param string $k key value
     * @param string $v value to store
     * @param boolean $overwrite whether or not to overwrite existing
     * @access private
     * @return void
     */
     private function loadErrorToSession($class, $k, $v, $overwrite = false) {
         $k = "errors";
         if($this->session != null) {
            $this->session->setClassError($class, $k, $v);
         } else {
             die("Where is the session");
         }
     }

}

?>