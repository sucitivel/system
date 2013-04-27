<?php

namespace Habari;

/**
 * A submit control based on FormControl for output via FormUI
 */
class FormControlDropbutton extends FormControl
{
	static $outpre = false;
	public $on_success = array();

	public function _extend() {
		$this->properties['type'] = 'hidden';
		$this->add_template_class('div', 'dropbutton dropbutton_control');
	}

	/**
	 * Set the actions of this dropbutton, the first action is the default action
	 * @param array $actions The actions to set, captions as keys, on_success methods as values
	 * @param bool $override Defaults to false. If true, override existing actions.  If false, merge with existing actions
	 * @return FormControlSubmit $this
	 */
	public function set_actions($new_actions, $override = false)
	{
		$actions = array();
		foreach($new_actions as $caption => $fn) {
			if(is_callable($fn)) {
				$href = '#' . Utils::slugify($caption);
			}
			else {
				$href = $fn;
			}
			$key = Utils::slugify($caption);
			$actions[$key] = array(
				'caption' => $caption,
				'fn' => $fn,
				'href' => $href,
			);
		}
		if(!$override) {
			$actions = array_merge($this->get_setting('actions', array()), $actions);
		}
		$this->set_settings(array('actions' => $actions));
		return $this;
	}

	/**
	 * Return the HTML/script required for this control.  Do it only once.
	 * @return string The HTML/javascript required for this control.
	 */
	public function pre_out()
	{
		$out = '';
		if ( !self::$outpre ) {
			self::$outpre = true;
			$out = <<<  CUSTOM_DROPBUTTON_JS
				<script type="text/javascript">
controls.init(function(){
	$('.dropbutton_control').each(function(){
		var self = $(this);
		self.find('.dropdown-menu').width(self.find('.primary').outerWidth()+self.find('.dropdown').outerWidth());
		self.on('click', 'a', function(){
			var a = $(this);
			self.find('input').val(a.attr('href').replace(/^.*#/, ''));
			self.closest('form').submit();
		});
		self.find('.dropdown').on('click', function(event){
			self.toggleClass('dropped');
			event.preventDefault();
			return false;
		});
	});
	$('body').on('click', function(){
		$('.dropbutton').removeClass('dropped');
	});
});
				</script>
CUSTOM_DROPBUTTON_JS;
		}
		return $out;
	}


	/**
	 * This control only executes its on_success callbacks when it was clicked
	 * @return bool|string A string to replace the rendering of the form with, or false
	 */
	public function do_success($form)
	{
		$actions = $this->get_setting('actions', array());
		if(isset($actions[$this->value])) {
			$actions[$this->value]['fn']($form);
		}
		return parent::do_success($form);
	}

	public function get(Theme $theme)
	{
		$this->vars['actions'] = $this->get_setting('actions', array());
		$this->set_template_properties('div', array('id' => $this->get_visualizer()));
		$this->add_template_class('ul', 'dropdown-menu');
		if(count($this->settings['actions']) > 1) {
			$this->add_template_class('div', 'has-drop');
		}
		else {
			$this->add_template_class('div', 'no-drop');
		}
		return parent::get($theme);
	}

	/**
	 * Returns the HTML id of the element that the control exposes as a target, for example, for labels
	 */
	public function get_visualizer()
	{
		return $this->get_id() . '_visualizer';
	}


}


?>